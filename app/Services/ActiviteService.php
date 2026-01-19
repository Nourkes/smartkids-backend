<?php
// app/Services/ActiviteService.php

namespace App\Services;

use App\Models\{Activite, ParticipationActivite};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActiviteService
{
    /* ========== TYPES ========== */

   public function typesPublic(): array
    {
        $column = DB::selectOne("
            SHOW COLUMNS 
            FROM activite 
            WHERE Field = 'type'
        ");

        preg_match("/^enum\((.*)\)$/", $column->Type, $matches);

        return collect(explode(',', $matches[1]))
            ->map(fn ($v) => trim($v, "'"))
            ->values()
            ->all();
    }

    /* ========== READ ========== */

    public function index(array $filtres): LengthAwarePaginator
    {
        $q = Activite::with(['educateurs','enfants']);

        if (!empty($filtres['date_debut'])) $q->where('date_activite','>=',$filtres['date_debut']);
        if (!empty($filtres['date_fin']))   $q->where('date_activite','<=',$filtres['date_fin']);
        if (!empty($filtres['type']))       $q->where('type',$filtres['type']);
        if (!empty($filtres['statut']))     $q->where('statut',$filtres['statut']);
        if (!empty($filtres['search']))     $q->where('nom','like','%'.$filtres['search'].'%');

        $sortBy    = $filtres['sort_by']    ?? 'date_activite';
        $sortOrder = $filtres['sort_order'] ?? 'asc';
        $perPage   = (int)($filtres['per_page'] ?? 15);

        return $q->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function show(Activite $a): Activite
    {
        return $a->load([
            'educateurs',
            'enfants' => function($q) {
                $q->withPivot(['statut_participation','remarques','note_evaluation','date_inscription','date_presence']);
            }
        ]);
    }

    /* ========== WRITE ========== */

    public function store(array $data, ?UploadedFile $image): Activite
    {
        if ($image) {
            $data['image'] = $image->store('activites', 'public');
        }

        return DB::transaction(function() use ($data){
            $ids = $data['educateur_ids'] ?? null;
            $a   = Activite::create($data);
            if (is_array($ids) && !empty($ids)) {
                $a->educateurs()->attach($ids);
            }
            return $a->load('educateurs','enfants');
        });
    }

    public function update(Activite $a, array $data, ?UploadedFile $image): Activite
    {
        return DB::transaction(function() use ($a,$data,$image){
            if ($image) {
                if ($a->image) Storage::disk('public')->delete($a->image);
                $data['image'] = $image->store('activites','public');
            }

            $a->update($data);

            if (array_key_exists('educateur_ids', $data)) {
                $a->educateurs()->sync($data['educateur_ids'] ?? []);
            }

            return $a->load('educateurs','enfants');
        });
    }

    public function destroy(Activite $a): void
    {
        if ($a->image) Storage::disk('public')->delete($a->image);
        $a->delete();
    }

    /* ========== INSCRIPTIONS / PRÉSENCES ========== */

    public function inscrire(Activite $a, int $enfantId, ?string $remarques): void
    {
        if ($a->capacite_max && $a->enfants()->count() >= $a->capacite_max) {
            abort(422, 'Capacité maximale atteinte');
        }
        if ($a->enfants()->where('enfant_id',$enfantId)->exists()) {
            abort(422, "L'enfant est déjà inscrit à cette activité");
        }

        // pivot: participation_activite
        $a->enfants()->attach($enfantId, [
            'statut_participation' => 'inscrit',
            'remarques'            => $remarques,
            'date_inscription'     => now(),
        ]);
    }

    public function desinscrire(Activite $a, int $enfantId): void
    {
        if (!$a->enfants()->where('enfant_id',$enfantId)->exists()) {
            abort(404, "L'enfant n'est pas inscrit à cette activité");
        }
        $a->enfants()->detach($enfantId);
    }

    public function marquerPresences(Activite $a, array $presences): void
    {
        DB::transaction(function() use ($a,$presences){
            foreach ($presences as $p) {
                $a->enfants()->updateExistingPivot($p['enfant_id'], [
                    'statut_participation' => $p['statut'],
                    'remarques'            => $p['remarques'] ?? null,
                    'note_evaluation'      => $p['note_evaluation'] ?? null,
                    'date_presence'        => $p['statut'] === 'present' ? now() : null,
                ]);
            }
        });
    }

    /* ========== STATS / OUTILS ========== */

    public function stats(): array
    {
        return [
            'total'                => Activite::count(),
            'planifiees'           => Activite::where('statut','planifiee')->count(),
            'en_cours'             => Activite::where('statut','en_cours')->count(),
            'terminees'            => Activite::where('statut','terminee')->count(),
            'annulees'             => Activite::where('statut','annulee')->count(),
            'cette_semaine'        => Activite::whereBetween('date_activite', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'ce_mois'              => Activite::whereMonth('date_activite', now()->month)->whereYear('date_activite', now()->year)->count(),
            'total_participations' => ParticipationActivite::count(),
            'total_presents'       => ParticipationActivite::where('statut_participation','present')->count(),
            'par_type'             => Activite::selectRaw('type, COUNT(*) as count')->whereNotNull('type')->groupBy('type')->get(),
            'a_venir'              => Activite::where('date_activite','>=', today())
                                               ->where('date_activite','<=', today()->addDays(7))
                                               ->where('statut','planifiee')->count(),
        ];
    }

    public function changeStatut(Activite $a, string $statut): Activite
    {
        $a->update(['statut'=>$statut]);
        return $a;
    }

    public function duplicate(Activite $a, array $data): Activite
    {
        return DB::transaction(function() use ($a,$data){
            $b = $a->replicate();
            $b->nom           = $a->nom.' (Copie)';
            $b->date_activite = $data['date_activite'];
            $b->heure_debut   = $data['heure_debut'] ?? $a->heure_debut;
            $b->heure_fin     = $data['heure_fin']   ?? $a->heure_fin;
            $b->statut        = 'planifiee';
            $b->save();

            $ids = $a->educateurs()->pluck('educateurs.id')->toArray();
            if (!empty($ids)) $b->educateurs()->attach($ids);

            return $b->load('educateurs');
        });
    }
}
