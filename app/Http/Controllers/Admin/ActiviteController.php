<?php
// app/Http/Controllers/Admin/ActiviteController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\ParticipationActivite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\ActiviteService;

class ActiviteController extends Controller
{
    /* ==================== LECTURE / LISTE / TYPES ==================== */

    public function types(): JsonResponse
    {
        $types = Activite::query()
            ->whereNotNull('type')
            ->select('type')->distinct()->orderBy('type')
            ->pluck('type')->values();

        return response()->json([
            'success' => true,
            'message' => 'Types récupérés',
            'data'    => Activite::TYPES, // garde ta constante telle quelle
        ]);
    }

    public function typesPublic(ActiviteService $service): JsonResponse
{
    return response()->json(
        $service->typesPublic()
    );
}

    public function index(Request $request): JsonResponse
    {
        $query = Activite::with(['educateurs', 'enfants']);

        if ($request->filled('date_debut')) $query->where('date_activite', '>=', $request->date_debut);
        if ($request->filled('date_fin'))   $query->where('date_activite', '<=', $request->date_fin);
        if ($request->filled('type'))       $query->where('type', $request->type);
        if ($request->filled('statut'))     $query->where('statut', $request->statut);
        if ($request->filled('search'))     $query->where('nom', 'like', '%'.$request->search.'%');

        $sortBy    = $request->get('sort_by', 'date_activite');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $activites = $query->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $activites]);
    }

    public function show(Activite $activite): JsonResponse
    {
        $activite->load([
            'educateurs',
            'enfants' => function ($q) {
                $q->withPivot(['statut_participation','remarques','note_evaluation','date_inscription','date_presence']);
            }
        ]);

        return response()->json(['success' => true, 'data' => $activite]);
    }

    /* ========================== CRÉATION =========================== */

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'             => 'required|string|max:255',
            'description'     => 'nullable|string',
            'type'            => 'nullable|string|max:100',
            'date_activite'   => 'required|date|after_or_equal:today',
            'heure_debut'     => 'required|date_format:H:i',
            'heure_fin'       => 'required|date_format:H:i|after:heure_debut',
            'prix'            => 'nullable|numeric|min:0',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:4096',
            'statut'          => 'nullable|in:planifiee,en_cours,terminee,annulee',
            'capacite_max'    => 'nullable|integer|min:1',
            'materiel_requis' => 'nullable|string',
            'consignes'       => 'nullable|string',
            'educateur_ids'   => 'nullable|array',
            'educateur_ids.*' => 'exists:educateurs,id',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('activites', 'public');
        }

        DB::beginTransaction();
        try {
            $activite = Activite::create($data);

            if ($request->filled('educateur_ids')) {
                $activite->educateurs()->attach($request->educateur_ids);
            }

            DB::commit();
            $activite->load('educateurs', 'enfants');

            return response()->json([
                'success' => true,
                'message' => 'Activité créée avec succès',
                'data'    => $activite,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la création de l'activité",
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* =========================== MISE À JOUR =========================== */

    public function update(Request $request, Activite $activite): JsonResponse
    {
        $data = $request->validate([
            'nom'             => 'sometimes|required|string|max:255',
            'description'     => 'nullable|string',
            'type'            => 'nullable|string|max:100',
            'date_activite'   => 'sometimes|date',
            'heure_debut'     => 'sometimes|date_format:H:i',
            'heure_fin'       => 'sometimes|date_format:H:i',
            'prix'            => 'nullable|numeric|min:0',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:4096',
            'statut'          => 'nullable|in:planifiee,en_cours,terminee,annulee',
            'capacite_max'    => 'nullable|integer|min:1',
            'materiel_requis' => 'nullable|string',
            'consignes'       => 'nullable|string',
            'educateur_ids'   => 'nullable|array',
            'educateur_ids.*' => 'exists:educateurs,id',
        ]);

        if (isset($data['heure_debut']) && isset($data['heure_fin']) && $data['heure_fin'] <= $data['heure_debut']) {
            return response()->json([
                'success' => false,
                'message' => "L'heure de fin doit être après l'heure de début",
            ], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->hasFile('image')) {
                if ($activite->image) {
                    Storage::disk('public')->delete($activite->image);
                }
                $data['image'] = $request->file('image')->store('activites', 'public');
            }

            $activite->update($data);

            if ($request->has('educateur_ids')) {
                $activite->educateurs()->sync($request->educateur_ids);
            }

            DB::commit();
            $activite->load('educateurs', 'enfants');

            return response()->json([
                'success' => true,
                'message' => 'Activité mise à jour avec succès',
                'data'    => $activite,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* =========================== SUPPRESSION =========================== */

    public function destroy(Activite $activite): JsonResponse
    {
        try {
            if ($activite->image) {
                Storage::disk('public')->delete($activite->image);
            }
            $activite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Activité supprimée avec succès',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ===================== ACTIONS SPÉCIFIQUES ===================== */

    // — Inscrire un enfant
    public function inscrireEnfant(Request $request, Activite $activite): JsonResponse
    {
        $data = $request->validate([
            'enfant_id' => 'required|exists:enfants,id',
            'remarques' => 'nullable|string',
        ]);

        if ($activite->capacite_max && $activite->enfants()->count() >= $activite->capacite_max) {
            return response()->json(['success' => false, 'message' => 'Capacité maximale atteinte'], 422);
        }

        if ($activite->enfants()->where('enfant_id', $data['enfant_id'])->exists()) {
            return response()->json(['success' => false, 'message' => "L'enfant est déjà inscrit à cette activité"], 422);
        }

        $activite->enfants()->attach($data['enfant_id'], [
            'statut_participation' => 'inscrit',
            'remarques'            => $data['remarques'] ?? null,
            'date_inscription'     => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Enfant inscrit avec succès']);
    }

    // — Désinscrire un enfant
    public function desinscrireEnfant(Activite $activite, int $enfantId): JsonResponse
    {
        if (! $activite->enfants()->where('enfant_id', $enfantId)->exists()) {
            return response()->json(['success' => false, 'message' => "L'enfant n'est pas inscrit à cette activité"], 404);
        }

        $activite->enfants()->detach($enfantId);

        return response()->json(['success' => true, 'message' => 'Enfant désinscrit avec succès']);
    }

    // — Marquer les présences
    public function marquerPresences(Request $request, Activite $activite): JsonResponse
    {
        $data = $request->validate([
            'presences'                 => 'required|array',
            'presences.*.enfant_id'     => 'required|exists:enfants,id',
            'presences.*.statut'        => 'required|in:present,absent,excuse',
            'presences.*.remarques'     => 'nullable|string',
            'presences.*.note_evaluation'=> 'nullable|integer|min:1|max:10',
        ]);

        DB::beginTransaction();
        try {
            foreach ($data['presences'] as $p) {
                $activite->enfants()->updateExistingPivot($p['enfant_id'], [
                    'statut_participation' => $p['statut'],
                    'remarques'            => $p['remarques'] ?? null,
                    'note_evaluation'      => $p['note_evaluation'] ?? null,
                    'date_presence'        => $p['statut'] === 'present' ? now() : null,
                ]);
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Présences marquées avec succès']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur lors du marquage des présences', 'error' => $e->getMessage()], 500);
        }
    }

    // — Statistiques
    public function stats(): JsonResponse
    {
        $stats = [
            'total'              => Activite::count(),
            'planifiees'         => Activite::where('statut', 'planifiee')->count(),
            'en_cours'           => Activite::where('statut', 'en_cours')->count(),
            'terminees'          => Activite::where('statut', 'terminee')->count(),
            'annulees'           => Activite::where('statut', 'annulee')->count(),
            'cette_semaine'      => Activite::whereBetween('date_activite', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count(),
            'ce_mois'            => Activite::whereMonth('date_activite', Carbon::now()->month)->whereYear('date_activite', Carbon::now()->year)->count(),
            'total_participations'=> ParticipationActivite::count(),
            'total_presents'     => ParticipationActivite::where('statut_participation', 'present')->count(),
        ];

        $stats['par_type'] = Activite::selectRaw('type, COUNT(*) as count')
            ->whereNotNull('type')
            ->groupBy('type')
            ->get();

        $stats['a_venir'] = Activite::where('date_activite', '>=', Carbon::today())
            ->where('date_activite', '<=', Carbon::today()->addDays(7))
            ->where('statut', 'planifiee')
            ->count();

        return response()->json(['success' => true, 'data' => $stats]);
    }

    // — Changer le statut
    public function changeStatut(Request $request, Activite $activite): JsonResponse
    {
        $data = $request->validate([
            'statut' => 'required|in:planifiee,en_cours,terminee,annulee',
        ]);

        $activite->update(['statut' => $data['statut']]);

        return response()->json(['success' => true, 'message' => 'Statut mis à jour avec succès', 'data' => $activite]);
    }

    // — Dupliquer
    public function duplicate(Request $request, Activite $activite): JsonResponse
    {
        $data = $request->validate([
            'date_activite' => 'required|date|after_or_equal:today',
            'heure_debut'   => 'nullable|date_format:H:i',
            'heure_fin'     => 'nullable|date_format:H:i',
        ]);

        DB::beginTransaction();
        try {
            $clone = $activite->replicate();
            $clone->nom            = $activite->nom.' (Copie)';
            $clone->date_activite  = $data['date_activite'];
            $clone->heure_debut    = $data['heure_debut'] ?? $activite->heure_debut;
            $clone->heure_fin      = $data['heure_fin']   ?? $activite->heure_fin;
            $clone->statut         = 'planifiee';
            $clone->save();

            $educateurIds = $activite->educateurs()->pluck('educateurs.id')->toArray();
            if (!empty($educateurIds)) {
                $clone->educateurs()->attach($educateurIds);
            }

            DB::commit();
            $clone->load('educateurs');

            return response()->json([
                'success' => true,
                'message' => 'Activité dupliquée avec succès',
                'data'    => $clone,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
