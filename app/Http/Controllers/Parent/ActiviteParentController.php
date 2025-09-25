<?php
// app/Http/Controllers/Parent/ActiviteParentController.php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\Enfant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ActiviteParentController extends Controller
{
    /**
     * Activités disponibles pour inscription
     * (disponible = date_activite >= aujourd'hui)
     */
    public function activitesDisponibles(Request $request): JsonResponse
    {
        $q = Activite::with(['educateurs'])
            ->whereDate('date_activite', '>=', now()->toDateString());

        // Filtres optionnels
        $type = (string) $request->input('type', '');
        if ($type !== '') {
            $q->where('type', $type);
        }

        $dateDebut = $request->input('date_debut');
        if (!empty($dateDebut)) {
            $q->whereDate('date_activite', '>=', $dateDebut);
        }

        $dateFin = $request->input('date_fin');
        if (!empty($dateFin)) {
            $q->whereDate('date_activite', '<=', $dateFin);
        }

        $search = (string) $request->input('search', '');
        if ($search !== '') {
            $q->where('nom', 'like', "%{$search}%");
        }

        $perPage = (int) $request->input('per_page', 10);

        $activites = $q->orderBy('date_activite', 'asc')
            ->orderBy('heure_debut', 'asc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $activites,
        ]);
        // NB: pagination Laravel renvoie déjà { data, links, meta }.
    }

    /**
     * Historique des activités d'un enfant
     */
    public function historiqueEnfant(Enfant $enfant): JsonResponse
    {
        $parentId = optional(Auth::user()->parent)->id;
        if ((int) $enfant->parent_id !== (int) $parentId) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $activites = $enfant->activites()
            ->withPivot([
                'statut_participation',
                'remarques',
                'note_evaluation',
                'date_inscription',
                'date_presence',
            ])
            ->orderBy('date_activite', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $activites,
        ]);
    }

    /**
     * Statistiques d’un enfant (sans colonne "statut")
     * - terminé = date_activite < aujourd'hui
     * - à venir  = date_activite > aujourd'hui
     */
    public function statistiquesEnfant(Enfant $enfant): JsonResponse
    {
        $parentId = optional(Auth::user()->parent)->id;
        if ((int) $enfant->parent_id !== (int) $parentId) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $total      = $enfant->activites()->count();

        $terminees  = $enfant->activites()
            ->whereDate('date_activite', '<', now()->toDateString())
            ->count();

        $aVenir     = $enfant->activites()
            ->whereDate('date_activite', '>', now()->toDateString())
            ->count();

        $presences  = $enfant->activites()
            ->wherePivot('statut_participation', 'present')
            ->count();

        $absences   = $enfant->activites()
            ->wherePivot('statut_participation', 'absent')
            ->count();

        $noteMoy    = $enfant->activites()
            ->whereNotNull('participation_activite.note_evaluation')
            ->avg('participation_activite.note_evaluation');

        $tauxPresence = $terminees > 0
            ? round(($presences / $terminees) * 100, 2)
            : 0;

        // Répartition par type
        $parType = $enfant->activites()
            ->selectRaw('type, COUNT(*) as count')
            ->whereNotNull('type')
            ->groupBy('type')
            ->get();

        // Dernières activités
        $dernieres = $enfant->activites()
            ->withPivot(['statut_participation', 'note_evaluation'])
            ->orderBy('date_activite', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_activites'     => $total,
                'activites_terminees' => $terminees,
                'activites_a_venir'   => $aVenir,
                'presences'           => $presences,
                'absences'            => $absences,
                'note_moyenne'        => $noteMoy,
                'taux_presence'       => $tauxPresence,
                'par_type'            => $parType,
                'dernieres_activites' => $dernieres,
            ],
        ]);
    }

    /**
     * Calendrier mensuel (sans champ "statut")
     */
    public function calendrierEnfant(Request $request, Enfant $enfant): JsonResponse
    {
        $parentId = optional(Auth::user()->parent)->id;
        if ((int) $enfant->parent_id !== (int) $parentId) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $mois  = (int) $request->input('mois',  Carbon::now()->month);
        $annee = (int) $request->input('annee', Carbon::now()->year);

        $activites = $enfant->activites()
            ->withPivot(['statut_participation', 'remarques', 'note_evaluation'])
            ->whereYear('date_activite', $annee)
            ->whereMonth('date_activite', $mois)
            ->orderBy('date_activite', 'asc')
            ->get()
            ->map(function ($a) {
                return [
                    'id'                   => $a->id,
                    'nom'                  => $a->nom,
                    'date'                 => Carbon::parse($a->date_activite)->format('Y-m-d'),
                    'heure_debut'          => $a->heure_debut,
                    'heure_fin'            => $a->heure_fin,
                    'type'                 => $a->type,
                    'statut_participation' => $a->pivot->statut_participation,
                    'note_evaluation'      => $a->pivot->note_evaluation,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'mois'      => $mois,
                'annee'     => $annee,
                'activites' => $activites,
            ],
        ]);
    }

    /**
     * Détails d'une activité avec la participation de l'enfant
     */
    public function detailsActiviteEnfant(Activite $activite, Enfant $enfant): JsonResponse
    {
        $parentId = optional(Auth::user()->parent)->id;
        if ((int) $enfant->parent_id !== (int) $parentId) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $participation = $activite->enfants()
            ->where('enfant_id', $enfant->id)
            ->withPivot([
                'statut_participation',
                'remarques',
                'note_evaluation',
                'date_inscription',
                'date_presence',
            ])
            ->first();

        if (!$participation) {
            return response()->json([
                'success' => false,
                'message' => "L'enfant n'est pas inscrit à cette activité",
            ], 404);
        }

        $activite->load('educateurs');

        return response()->json([
            'success' => true,
            'data' => [
                'activite' => $activite,
                'participation' => [
                    'statut_participation' => $participation->pivot->statut_participation,
                    'remarques'            => $participation->pivot->remarques,
                    'note_evaluation'      => $participation->pivot->note_evaluation,
                    'date_inscription'     => $participation->pivot->date_inscription,
                    'date_presence'        => $participation->pivot->date_presence,
                ],
            ],
        ]);
    }
}
