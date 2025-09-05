<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Http\Requests\ClasseRequest;
use App\Http\Resources\ClasseResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClasseController extends Controller
{
    /**
     * Liste toutes les classes
     * GET /api/admin/classes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Classe::query();

            // Filtrage par niveau si fourni
            if ($request->has('niveau') && !empty($request->niveau)) {
                $query->where('niveau', 'like', '%' . $request->niveau . '%');
            }

            // Filtrage par nom si fourni
            if ($request->has('nom') && !empty($request->nom)) {
                $query->where('nom', 'like', '%' . $request->nom . '%');
            }

            // Inclure les relations si demandées
            $with = [];
            if ($request->has('with_enfants') && $request->boolean('with_enfants')) {
                $with[] = 'enfants';
            }
            if ($request->has('with_educateurs') && $request->boolean('with_educateurs')) {
                $with[] = 'educateurs.user';
            }
            if ($request->has('with_matieres') && $request->boolean('with_matieres')) {
                $with[] = 'matieres';
            }

            if (!empty($with)) {
                $query->with($with);
            }

            // Tri par défaut par niveau puis par nom
            $query->orderBy('niveau')->orderBy('nom');

            // Pagination ou récupération complète
            if ($request->has('paginate') && $request->boolean('paginate')) {
                $perPage = $request->get('per_page', 15);
                $classes = $query->paginate($perPage);
                
                return response()->json([
                    'success' => true,
                    'data' => ClasseResource::collection($classes->items()),
                    'meta' => [
                        'current_page' => $classes->currentPage(),
                        'last_page' => $classes->lastPage(),
                        'per_page' => $classes->perPage(),
                        'total' => $classes->total(),
                    ],
                ]);
            } else {
                $classes = $query->get();
                return response()->json([
                    'success' => true,
                    'data' => ClasseResource::collection($classes),
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche une classe spécifique
     * GET /api/admin/classes/{id}
     */
    public function show(Request $request, Classe $classe): JsonResponse
    {
        try {
            // Inclure les relations si demandées
            $with = [];
            if ($request->has('with_enfants') && $request->boolean('with_enfants')) {
                $with[] = 'enfants.parents.user';
            }
            if ($request->has('with_educateurs') && $request->boolean('with_educateurs')) {
                $with[] = 'educateurs.user';
            }
            if ($request->has('with_matieres') && $request->boolean('with_matieres')) {
                $with[] = 'matieres';
            }

            if (!empty($with)) {
                $classe->load($with);
            }

            return response()->json([
                'success' => true,
                'data' => new ClasseResource($classe),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la classe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée une nouvelle classe
     * POST /api/admin/classes
     */
    public function store(ClasseRequest $request): JsonResponse
    {
        try {
            $classe = Classe::create($request->validated());

            // Associer des éducateurs si fournis
            if ($request->has('educateur_ids') && is_array($request->educateur_ids)) {
                $classe->educateurs()->attach($request->educateur_ids);
            }

            // Associer des matières si fournies
            if ($request->has('matiere_ids') && is_array($request->matiere_ids)) {
                $classe->matieres()->attach($request->matiere_ids);
            }

            $classe->load('educateurs.user', 'matieres');

            return response()->json([
                'success' => true,
                'message' => 'Classe créée avec succès',
                'data' => new ClasseResource($classe),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la classe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour une classe
     * PUT/PATCH /api/admin/classes/{id}
     */
    public function update(ClasseRequest $request, Classe $classe): JsonResponse
    {
        try {
            $classe->update($request->validated());

            // Mettre à jour les éducateurs si fournis
            if ($request->has('educateur_ids')) {
                if (is_array($request->educateur_ids)) {
                    $classe->educateurs()->sync($request->educateur_ids);
                } else {
                    $classe->educateurs()->detach();
                }
            }

            // Mettre à jour les matières si fournies
            if ($request->has('matiere_ids')) {
                if (is_array($request->matiere_ids)) {
                    $classe->matieres()->sync($request->matiere_ids);
                } else {
                    $classe->matieres()->detach();
                }
            }

            $classe->load('educateurs.user', 'matieres');

            return response()->json([
                'success' => true,
                'message' => 'Classe mise à jour avec succès',
                'data' => new ClasseResource($classe),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la classe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime une classe
     * DELETE /api/admin/classes/{id}
     */
    public function destroy(Classe $classe): JsonResponse
    {
        try {
            // Vérifier s'il y a des enfants dans cette classe
            if ($classe->enfants()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer une classe qui contient des enfants',
                ], 422);
            }

            // Détacher les relations avant suppression
            $classe->educateurs()->detach();
            $classe->matieres()->detach();

            $classe->delete();

            return response()->json([
                'success' => true,
                'message' => 'Classe supprimée avec succès',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la classe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des classes
     * GET /api/admin/classes/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_classes' => Classe::count(),
                'classes_avec_enfants' => Classe::has('enfants')->count(),
                'classes_vides' => Classe::doesntHave('enfants')->count(),
                'classes_par_niveau' => Classe::selectRaw('niveau, COUNT(*) as count')
                    ->groupBy('niveau')
                    ->orderBy('niveau')
                    ->get()
                    ->pluck('count', 'niveau'),
                'capacite_totale' => Classe::sum('capacite_max'),
                'enfants_inscrits' => \App\Models\Enfant::whereNotNull('classe_id')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}