<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignEducateurToClasseRequest;
use App\Http\Resources\ClasseResource;
use App\Http\Resources\EducateurResource;
use App\Models\Classe;
use App\Models\Educateur;
use App\Services\ClasseEducateurService;
use Illuminate\Http\Request;

class ClasseEducateurController extends Controller
{
    protected $classeEducateurService;

    public function __construct(ClasseEducateurService $classeEducateurService)
    {
        $this->classeEducateurService = $classeEducateurService;
    }

    /**
     * Assigner un éducateur à une classe (Admin uniquement)
     */
    public function assignEducateur(AssignEducateurToClasseRequest $request)
    {
        try {
            $result = $this->classeEducateurService->assignEducateurToClasse(
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Éducateur assigné à la classe avec succès',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Retirer un éducateur d'une classe (Admin uniquement)
     */
    public function removeEducateur(Request $request)
    {
        $request->validate([
            'educateur_id' => 'required|exists:educateurs,id',
            'classe_id' => 'required|exists:classe,id',
        ]);

        try {
            $this->classeEducateurService->removeEducateurFromClasse(
                $request->educateur_id,
                $request->classe_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Éducateur retiré de la classe avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtenir les éducateurs d'une classe spécifique
     */
    public function getEducateursByClasse(Classe $classe)
    {
        try {
            $user = auth()->user();
            
            if ($user->isEducateur()) {
                $educateur = $user->educateur;
                if (!$educateur->classes()->where('classe_id', $classe->id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vous n\'avez pas accès à cette classe'
                    ], 403);
                }
            }

            $educateurs = $this->classeEducateurService->getEducateursByClasse($classe->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'classe' => new ClasseResource($classe),
                    'educateurs' => EducateurResource::collection($educateurs)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les classes d'un éducateur spécifique
     */
    public function getClassesByEducateur(Educateur $educateur)
    {
        try {
            $user = auth()->user();
            
            if ($user->isEducateur() && $user->educateur->id !== $educateur->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez consulter que vos propres classes'
                ], 403);
            }

            $classes = $this->classeEducateurService->getClassesByEducateur($educateur->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'educateur' => new EducateurResource($educateur->load('user')),
                    'classes' => ClasseResource::collection($classes)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir tous les éducateurs non assignés à une classe spécifique (Admin uniquement)
     */
    public function getAvailableEducateurs(Classe $classe)
    {
        try {
            $educateurs = $this->classeEducateurService->getAvailableEducateursByClasse($classe->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'classe' => new ClasseResource($classe),
                    'educateurs_disponibles' => EducateurResource::collection($educateurs)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Changer l'éducateur d'une classe (Admin uniquement)
     */
    public function changeEducateur(Request $request)
    {
        $request->validate([
            'ancien_educateur_id' => 'required|exists:educateurs,id',
            'nouveau_educateur_id' => 'required|exists:educateurs,id',
            'classe_id' => 'required|exists:classe,id',
        ]);

        try {
            $result = $this->classeEducateurService->changeEducateurForClasse(
                $request->ancien_educateur_id,
                $request->nouveau_educateur_id,
                $request->classe_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Éducateur changé avec succès',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtenir le résumé des affectations (Admin uniquement)
     */
    public function getAffectationsResume()
    {
        try {
            $resume = $this->classeEducateurService->getAffectationsResume();

            return response()->json([
                'success' => true,
                'data' => [
                    'affectations' => $resume,
                    'statistiques' => $this->classeEducateurService->getAffectationsStats()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assigner plusieurs éducateurs à une classe en une fois (Admin uniquement)
     */
    public function assignMultipleEducateurs(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|exists:classe,id',
            'educateurs_ids' => 'required|array|min:1',
            'educateurs_ids.*' => 'exists:educateurs,id',
        ]);

        try {
            $result = $this->classeEducateurService->assignMultipleEducateursToClasse(
                $request->classe_id,
                $request->educateurs_ids
            );

            return response()->json([
                'success' => true,
                'message' => 'Éducateurs assignés avec succès',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtenir les classes de l'éducateur connecté
     */
    public function getMesClasses()
    {
        try {
            $user = auth()->user();
            
            if (!$user->isEducateur()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux éducateurs'
                ], 403);
            }

            $classes = $this->classeEducateurService->getClassesByEducateur($user->educateur->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'educateur' => new EducateurResource($user->educateur->load('user')),
                    'mes_classes' => ClasseResource::collection($classes),
                    'nombre_classes' => $classes->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}