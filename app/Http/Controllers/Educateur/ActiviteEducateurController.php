<?php
// app/Http/Controllers/Educateur/ActiviteEducateurController.php

namespace App\Http\Controllers\Educateur;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\ParticipationActivite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActiviteEducateurController extends Controller
{
    /**
     * Liste des activités assignées à l'éducateur connecté
     */
    public function mesActivites(Request $request): JsonResponse
    {
        $educateur = Auth::user()->educateur;
        
        if (!$educateur) {
            return response()->json([
                'success' => false,
                'message' => 'Profil éducateur non trouvé'
            ], 404);
        }

        $query = $educateur->activites()->with(['enfants' => function($q) {
            $q->withPivot(['statut_participation', 'remarques', 'note_evaluation']);
        }]);

        // Filtres
        if ($request->filled('date_debut')) {
            $query->where('date_activite', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->where('date_activite', '<=', $request->date_fin);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Par défaut, trier par date
        $query->orderBy('date_activite', 'asc');

        $activites = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $activites
        ]);
    }

    /**
     * Détails d'une activité spécifique
     */
    public function voirActivite(Activite $activite): JsonResponse
    {
        $educateur = Auth::user()->educateur;

        // Vérifier que l'éducateur est assigné à cette activité
        if (!$educateur->activites()->where('activite_id', $activite->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        $activite->load([
            'enfants' => function($query) {
                $query->withPivot([
                    'statut_participation', 
                    'remarques', 
                    'note_evaluation',
                    'date_inscription',
                    'date_presence'
                ]);
            },
            'educateurs'
        ]);

        return response()->json([
            'success' => true,
            'data' => $activite
        ]);
    }

    /**
     * Marquer les présences pour une activité
     */
    public function marquerPresences(Request $request, Activite $activite): JsonResponse
    {
        $educateur = Auth::user()->educateur;

        // Vérifier l'autorisation
        if (!$educateur->activites()->where('activite_id', $activite->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        $request->validate([
            'presences' => 'required|array',
            'presences.*.enfant_id' => 'required|exists:enfants,id',
            'presences.*.statut' => 'required|in:present,absent,excuse',
            'presences.*.remarques' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->presences as $presence) {
                // Vérifier que l'enfant est inscrit à cette activité
                if (!$activite->enfants()->where('enfant_id', $presence['enfant_id'])->exists()) {
                    continue;
                }

                $activite->enfants()->updateExistingPivot($presence['enfant_id'], [
                    'statut_participation' => $presence['statut'],
                    'remarques' => $presence['remarques'] ?? null,
                    'date_presence' => $presence['statut'] === 'present' ? now() : null
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Présences marquées avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage des présences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Évaluer les enfants (notes)
     */
    public function evaluerEnfants(Request $request, Activite $activite): JsonResponse
    {
        $educateur = Auth::user()->educateur;

        if (!$educateur->activites()->where('activite_id', $activite->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette activité'
            ], 403);
        }

        $request->validate([
            'evaluations' => 'required|array',
            'evaluations.*.enfant_id' => 'required|exists:enfants,id',
            'evaluations.*.note_evaluation' => 'required|integer|min:1|max:10',
            'evaluations.*.remarques' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->evaluations as $evaluation) {
                if ($activite->enfants()->where('enfant_id', $evaluation['enfant_id'])->exists()) {
                    $activite->enfants()->updateExistingPivot($evaluation['enfant_id'], [
                        'note_evaluation' => $evaluation['note_evaluation'],
                        'remarques' => $evaluation['remarques'] ?? null
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Évaluations enregistrées avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement des évaluations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Planning des activités de l'éducateur
     */
    public function planning(Request $request): JsonResponse
    {
        $educateur = Auth::user()->educateur;
        
        $dateDebut = $request->get('date_debut', Carbon::now()->startOfWeek());
        $dateFin = $request->get('date_fin', Carbon::now()->endOfWeek());

        $activites = $educateur->activites()
            ->wher