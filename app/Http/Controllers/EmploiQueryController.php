<?php

namespace App\Http\Controllers;

use App\Models\EmploiTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmploiQueryController extends Controller
{
    public function byClasse(Request $r, int $classeId)
    {
        $weekStart = $r->query('week_start')
            ? Carbon::parse($r->query('week_start'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $tpl = EmploiTemplate::where('classe_id', $classeId)
            ->where('status', 'published')
            ->whereDate('period_start', '<=', $weekStart)
            ->whereDate('period_end', '>=', $weekStart)
            ->whereDate('effective_from', '<=', $weekStart)
            ->orderByDesc('effective_from')->orderByDesc('version')
            ->with([
                'slots.matiere',
                'slots.educateur.user',
                'slots.salle',
                'classe',            // si tu veux niveau/nom
            ])
            ->first();

        if (!$tpl) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // On "aplatit" les champs utiles pour le front
        $slots = $tpl->slots->sortBy(fn ($s) => sprintf('%d-%s', $s->jour_semaine, $s->debut))
            ->values()
            ->map(function ($s) use ($tpl) {
                $educ = $s->educateur;
                $user = $educ?->user;
                $salle = $s->salle;

                return [
                    'id'                 => (int) $s->id,
                    'emploi_template_id' => (int) $s->emploi_template_id,
                    'jour_semaine'       => (int) $s->jour_semaine,
                    'debut'              => $s->debut,
                    'fin'                => $s->fin,
                    'matiere_id'         => (int) $s->matiere_id,
                    'educateur_id'       => (int) $s->educateur_id,
                    // ⚠️ ne pas convertir 0 en null par erreur
                    'salle_id'           => is_null($s->salle_id) ? null : (int) $s->salle_id,
                    'status'             => $s->status,

                    // libellés "friendly"
                    'matiere_nom'   => $s->matiere->nom ?? null,
                    'educateur_nom' => $user->name ?? ($educ->nom ?? null),
                    'salle_code'    => $salle->code ?? null,
                    'salle_nom'     => $salle->nom ?? null,

                    // optionnels (si utiles côté app)
                    'classe_niveau' => $tpl->classe->niveau ?? null,
                    'classe_nom'    => $tpl->classe->nom ?? null,

                    'created_at'     => $s->created_at,
                    'updated_at'     => $s->updated_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $slots]);
    }
}
