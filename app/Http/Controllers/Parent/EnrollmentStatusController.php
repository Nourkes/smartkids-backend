<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EnrollmentStatusController extends Controller
{
    /**
     * Vérifier le statut d'inscription pour l'année en cours
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStatus(Request $request)
    {
        $parent = $request->user()->parent;
        $currentYear = config('smartkids.current_academic_year');

        if (!$parent) {
            return response()->json([
                'error' => 'Parent non trouvé'
            ], 404);
        }

        // Récupérer tous les enfants du parent avec leurs inscriptions de l'année courante
        $enfants = $parent->enfants()->with([
            'inscriptions' => function ($q) use ($currentYear) {
                $q->where('annee_scolaire', $currentYear);
            }
        ])->get();

        // Mapper le statut pour chaque enfant
        $status = $enfants->map(function ($enfant) use ($currentYear) {
            $inscription = $enfant->inscriptions->first();

            return [
                'enfant_id' => $enfant->id,
                'nom' => $enfant->nom,
                'prenom' => $enfant->prenom,
                'nom_complet' => $enfant->prenom . ' ' . $enfant->nom,
                'has_active_enrollment' => $inscription ? true : false,
                'enrollment_status' => $inscription?->statut,
                'classe_id' => $inscription?->classe_id,
            ];
        });

        return response()->json([
            'current_year' => $currentYear,
            'enfants' => $status,
            'has_any_active' => $status->where('has_active_enrollment', true)->count() > 0,
        ]);
    }
}
