<?php
// app/Http/Controllers/Parent/ActiviteParentController.php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\Enfant;
use App\Models\ParticipationActivite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActiviteParentController extends Controller
{
    /**
     * Liste des activités disponibles pour inscription
     */
    public function activitesDisponibles(Request $request): JsonResponse
    {
        $query = Activite::with(['educateurs'])
            ->where('statut', 'planifiee')
            ->where('date_activite', '>=', today());

        // Filtres
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_debut')) {
            $query->where('date_activite', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date_activite', '<=', $request->date_fin);
        }

        // Recherche
        if ($request->filled('search')) {
            $query->where('nom', 'like', '%' . $request->search . '%');
        }

        $activites = $query->orderBy('date_activite', 'asc')
                          ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $activites
        ]);
    }

    /**
     * Mes enfants et leurs activités
     */
    public function activitesEnfants(): JsonResponse
    {
        $parent = Auth::user()->parent;
        
        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Profil parent non trouvé'
            ], 404);
        }

        $enfants = $parent->enfants()->with([
            'activites' => function($query) {
                $query->withPivot([
                    'statut_participation',
                    'remarques',
                    'note_evaluation',
                    'date_inscription',
                    'date_presence'
                ])->orderBy('date_activite', 'desc');
            }
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $enfants
        ]);
    }

    /**
     * Historique des activités d'un enfant
     */
    public function historiqueEnfant(Enfant $enfant): JsonResponse
    {
        // Vérifier que l'enfant appartient au parent connecté
        if ($enfant->parent_id !== Auth::user()->parent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $activites = $enfant->activites()
            ->withPivot([
                'statut_participation',
                'remarques', 
                'note_evaluation',
                'date_inscription',
                'date_presence'
            ])
            ->orderBy('date_activite', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $activites
        ]);
    }

    /**
     * Inscrire un enfant à une activité
     */
    public function inscrireEnfant(Request $request): JsonResponse
    {
        $request->validate([
            'enfant_id' => 'required|exists:enfants,id',
            'activite_id' => 'required|exists:activite,id'
        ]);

        $parent = Auth::user()->parent;
        $enfant = Enfant::find($request->enfant_id);
        $activite = Activite::find($request->activite_id);

        // Vérifier que l'enfant appartient au parent
        if ($enfant->parent_id !== $parent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cet enfant ne vous appartient pas'
            ], 403);
        }

        // Vérifications d'éligibilité
        if (!$activite->peutAccepterInscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette activité n\'accepte plus d\'inscriptions'
            ], 422);
        }

        // Vérifier si déjà inscrit
        if ($activite->enfantEstInscrit($enfant->id)) {
            return response()->json([
                'success' => false,
                'message' => 'L\'enfant est déjà inscrit à cette activité'
            ], 422);
        }

        try {
            $activite->enfants()->attach($enfant->id, [
                'statut_participation' => 'inscrit',
                'date_inscription' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inscription réalisée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Désinscrire un enfant d'une activité
     */
    public function desinscrireEnfant(Request $request): JsonResponse
    {
        $request->validate([
            'enfant_id' => 'required|exists:enfants,id',
            'activite_id' => 'required|exists:activite,id'
        ]);

        $parent = Auth::user()->parent;
        $enfant = Enfant::find($request->enfant_id);
        $activite = Activite::find($request->activite_id);

        // Vérifier que l'enfant appartient au parent
        if ($enfant->parent_id !== $parent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cet enfant ne vous appartient pas'
            ], 403);
        }

        // Vérifier l'inscription
        if (!$activite->enfantEstInscrit($enfant->id)) {
            return response()->json([
                'success' => false,
                'message' => 'L\'enfant n\'est pas inscrit à cette activité'
            ], 404);
        }

        // Vérifier si la désinscription est encore possible
        if ($activite->date_activite <= today()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de se désinscrire, l\'activité a déjà eu lieu ou est en cours'
            ], 422);
        }

        try {
            $activite->enfants()->detach($enfant->id);

            return response()->json([
                'success' => true,
                'message' => 'Désinscription réalisée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la désinscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des activités pour un enfant
     */
    public function statistiquesEnfant(Enfant $enfant): JsonResponse
    {
        // Vérifier que l'enfant appartient au parent connecté
        if ($enfant->parent_id !== Auth::user()->parent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $stats = [
            'total_activites' => $enfant->activites()->count(),
            'activites_terminees' => $enfant->activites()
                ->where('statut', 'terminee')
                ->count(),
            'presences' => $enfant->activites()
                ->wherePivot('statut_participation', 'present')
                ->count(),
            'absences' => $enfant->activites()
                ->wherePivot('statut_participation', 'absent')
                ->count(),
            'activites_a_venir' => $enfant->activites()
                ->where('date_activite', '>', today())
                ->where('statut', 'planifiee')
                ->count(),
            'note_moyenne' => $enfant->activites()
                ->whereNotNull('participation_activite.note_evaluation')
                ->avg('participation_activite.note_evaluation'),
            'taux_presence' => 0
        ];

        // Calculer le taux de présence
        $totalTerminees = $stats['activites_terminees'];
        if ($totalTerminees > 0) {
            $stats['taux_presence'] = round(($stats['presences'] / $totalTerminees) * 100, 2);
        }

        // Activités par type
        $stats['par_type'] = $enfant->activites()
            ->selectRaw('type, COUNT(*) as count')
            ->whereNotNull('type')
            ->groupBy('type')
            ->get();

        // Dernières activités
        $stats['dernieres_activites'] = $enfant->activites()
            ->withPivot(['statut_participation', 'note_evaluation'])
            ->orderBy('date_activite', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Calendrier des activités pour un enfant
     */
    public function calendrierEnfant(Request $request, Enfant $enfant): JsonResponse
    {
        // Vérifier que l'enfant appartient au parent connecté
        if ($enfant->parent_id !== Auth::user()->parent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $mois = $request->get('mois', Carbon::now()->month);
        $annee = $request->get('annee', Carbon::now()->year);

        $activites = $enfant->activites()
            ->withPivot(['statut_participation', 'remarques', 'note_evaluation'])
            ->whereMonth('date_activite', $mois)
            ->whereYear('date_activite', $annee)
            ->orderBy('date_activite', 'asc')
            ->get()
            ->map(function ($activite) {
                return [
                    'id' => $activite->id,
                    'nom' => $activite->nom,
                    'date' => $activite->date_activite->format('Y-m-d'),
                    'heure_debut' => $activite->heure_debut,
                    'heure_fin' => $activite->heure_fin,
                    'statut' => $activite->statut,
                    'statut_participation' => $activite->pivot->statut_participation,
                    'note_evaluation' => $activite->pivot->note_evaluation,
                    'type' => $activite->type
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'mois' => $mois,
                'annee' => $annee,
                'activites' => $activites
            ]
        ]);
    }

    /**
     * Détails d'une activité avec la participation de l'enfant
     */
    public function detailsActiviteEnfant(Activite $activite, Enfant $enfant): JsonResponse
    {
        // Vérifier que l'enfant appartient au parent connecté
        if ($enfant->parent_id !== Auth::user()->parent->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        // Récupérer l'activité avec les détails de participation
        $participation = $activite->enfants()
            ->where('enfant_id', $enfant->id)
            ->withPivot([
                'statut_participation',
                'remarques',
                'note_evaluation',
                'date_inscription',
                'date_presence'
            ])
            ->first();

        if (!$participation) {
            return response()->json([
                'success' => false,
                'message' => 'L\'enfant n\'est pas inscrit à cette activité'
            ], 404);
        }

        $activite->load('educateurs');

        return response()->json([
            'success' => true,
            'data' => [
                'activite' => $activite,
                'participation' => [
                    'statut_participation' => $participation->pivot->statut_participation,
                    'remarques' => $participation->pivot->remarques,
                    'note_evaluation' => $participation->pivot->note_evaluation,
                    'date_inscription' => $participation->pivot->date_inscription,
                    'date_presence' => $participation->pivot->date_presence
                ]
            ]
        ]);
    }
}