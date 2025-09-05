<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enfant;
use App\Models\ParentModel;
use App\Models\Classe;
use App\Http\Resources\EnfantResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class EnfantController extends Controller
{
    /**
     * 📋 Afficher la liste des enfants avec filtres
     */
public function index(Request $request): JsonResponse 
{
    try {
        $query = Enfant::with(['parents', 'classe']);
        
        // Vos filtres existants...
        
        // 📄 Pagination conditionnelle
        if ($request->boolean('paginate')) {
            $perPage = $request->get('per_page', 15);
            $enfants = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => EnfantResource::collection($enfants->items()),
                'meta' => [
                    'current_page' => $enfants->currentPage(),
                    'last_page' => $enfants->lastPage(),
                    'per_page' => $enfants->perPage(),
                    'total' => $enfants->total(),
                ]
            ]);
        } else {
            // Sans pagination
            $enfants = $query->get();
            return response()->json([
                'success' => true,
                'data' => EnfantResource::collection($enfants),
            ]);
        }
        
    } catch (\Exception $e) {
        Log::error('Erreur liste enfants: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des enfants'
        ], 500);
    }
}
    /**
     * 👁️ Afficher un enfant spécifique
     */
    public function show(Enfant $enfant): JsonResponse
    {
    
        try {
            $enfant->load(['parents', 'classe']);
            
            return response()->json([
                'success' => true,
                'data' => new EnfantResource($enfant)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enfant non trouvé'
            ], 404);
        }
    }
public function storeWithParent(Request $request)
{
    $validated = $request->validate([
        // Enfant
        'prenom' => 'required|string|max:255',
        'nom' => 'required|string|max:255',
        'date_naissance' => 'required|date',
        'sexe' => 'required|string|in:garçon,fille',
        'classe_id' => 'nullable|exists:classe,id',
        'allergies' => 'nullable|string',
        'remarques_medicales' => 'nullable|string',

        // Parent
        'parent.prenom' => 'required|string|max:255',
        'parent.nom' => 'required|string|max:255',
        'parent.email' => 'required|email|unique:users,email',
        'parent.password' => 'required|string|min:8',
        'parent.telephone' => 'required|string|max:20',
        'parent.adresse' => 'nullable|string|max:255',
        'parent.profession' => 'nullable|string|max:255',
        'parent.contact_urgence_nom' => 'nullable|string|max:255',
        'parent.contact_urgence_telephone' => 'nullable|string|max:20',
    ]);

    // 1. Créer un utilisateur pour le parent
    $user = User::create([
        'name' => $validated['parent']['prenom'] . ' ' . $validated['parent']['nom'],
        'email' => $validated['parent']['email'],
        'password' => bcrypt($validated['parent']['password']),
        'role' => 'parent',
    ]);

    // 2. Créer le parent lié à l'utilisateur
    $parent = ParentModel::create([
        'user_id' => $user->id,
        'telephone' => $validated['parent']['telephone'],
        'adresse' => $validated['parent']['adresse'] ?? null,
        'profession' => $validated['parent']['profession'] ?? null,
        'contact_urgence_nom' => $validated['parent']['contact_urgence_nom'] ?? null,
        'contact_urgence_telephone' => $validated['parent']['contact_urgence_telephone'] ?? null,
    ]);

    // 3. Créer l’enfant
    $enfant = Enfant::create([
        'prenom' => $validated['prenom'],
        'nom' => $validated['nom'],
        'date_naissance' => $validated['date_naissance'],
        'sexe' => $validated['sexe'],
        'classe_id' => $validated['classe_id'] ?? null,
        'allergies' => $validated['allergies'] ?? null,
        'remarques_medicales' => $validated['remarques_medicales'] ?? null,
    ]);

    // 4. Lier le parent à l’enfant
    $enfant->parents()->attach($parent->id);

    return response()->json([
        'message' => 'Enfant et parent ajoutés avec succès.',
        'enfant' => $enfant,
        'parent' => $parent,
    ], 201);
}

    /**
     * ➕ Créer un nouvel enfant
     */
    public function store(Request $request): JsonResponse
    {


        // 🔒 Validation
        $rules = [
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'date_naissance' => 'required|date|before:today',
            'sexe' => 'required|in:garçon,fille',
            
            // Relations
            'classe_id' => 'nullable|exists:classe,id',
            'parents' => 'required|array|min:1',
            'parents.*' => 'exists:parents,id',
            
            // Informations médicales
            'allergies' => 'nullable|string',
            'remarques_medicales' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            Log::info('=== CREATION ENFANT ===');
            Log::info('Données reçues', $request->all());

            // Créer l'enfant
            $enfantData = $request->except(['parents']);
            
            $enfant = Enfant::create($enfantData);
            Log::info('Enfant créé avec ID: ' . $enfant->id);

            // Associer les parents
            $enfant->parents()->attach($request->parents);
            Log::info('Parents associés', $request->parents);

            DB::commit();

            // Charger les relations pour la réponse
            $enfant->load(['parents', 'classe']);

            Log::info('=== ENFANT CREE AVEC SUCCES ===');

            return response()->json([
                'success' => true,
                'data' => new EnfantResource($enfant),
                'message' => 'Enfant créé avec succès'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création enfant: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'enfant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✏️ Mettre à jour un enfant
     */
    public function update(Request $request, Enfant $enfant): JsonResponse
    {


        // 🔒 Validation
        $rules = [
            'prenom' => 'sometimes|string|max:255',
            'nom' => 'sometimes|string|max:255',
            'date_naissance' => 'sometimes|date|before:today',
            'sexe' => 'sometimes|in:garçon,fille',
            
            // Relations
            'classe_id' => 'nullable|exists:classe,id',
            'parents' => 'sometimes|array|min:1',
            'parents.*' => 'exists:parents,id',
            
            // Informations médicales
            'allergies' => 'nullable|string',
            'remarques_medicales' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            Log::info('=== MISE A JOUR ENFANT ===');
            Log::info('Enfant ID: ' . $enfant->id);
            Log::info('Données reçues', $request->all());

            // Mettre à jour les données de l'enfant
            $enfantData = $request->except(['parents']);
            $enfant->update($enfantData);
            Log::info('Données enfant mises à jour');

            // Synchroniser les parents si fournis
            if ($request->has('parents')) {
                $enfant->parents()->sync($request->parents);
                Log::info('Parents synchronisés', $request->parents);
            }

            DB::commit();

            // Recharger les relations
            $enfant->refresh();
            $enfant->load(['parents', 'classe']);

            Log::info('=== ENFANT MIS A JOUR AVEC SUCCES ===');

            return response()->json([
                'success' => true,
                'data' => new EnfantResource($enfant),
                'message' => 'Enfant mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur mise à jour enfant: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'enfant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🗑️ Supprimer un enfant
     */

// use App\Models\Enfant;

public function destroy(Enfant $enfant): JsonResponse
{
    

    try {
        DB::beginTransaction();

        Log::info('=== SUPPRESSION ENFANT ===', ['enfant_id' => $enfant->id, 'nom' => $enfant->nom, 'prenom' => $enfant->prenom]);

        // 1) Mémoriser les parents AVANT de détacher (avec leur user)
        $parents = $enfant->parents()->with('user')->get();
        Log::info('Parents liés (avant détachement)', ['count' => $parents->count(), 'ids' => $parents->pluck('id')->all()]);

        // 2) Détacher les parents de l'enfant (table pivot)
        $detached = $enfant->parents()->detach();
        Log::info('Parents détachés', ['detached_count' => $detached]);

        // 3) Supprimer l'enfant
        $enfantDeleted = $enfant->delete();
        Log::info('Enfant supprimé', ['success' => (bool) $enfantDeleted]);

        // 4) Traiter chaque parent : s'il n'a plus d'enfants -> supprimer parent + user
        foreach ($parents as $parent) {
            $hasOtherKids = $parent->enfants()->exists(); // requête fraiche après détachement
            Log::info('Vérif parent orphelin', ['parent_id' => $parent->id, 'has_other_kids' => $hasOtherKids]);

            if (!$hasOtherKids) {
                // D'abord supprimer le parent (sécurise si l'ON DELETE CASCADE n'est pas configuré)
                $parentDeleted = $parent->delete();
                Log::info('Parent supprimé (orphelin)', ['parent_id' => $parent->id, 'success' => (bool) $parentDeleted]);

                // Puis supprimer le user associé
                if ($parent->user) {
                    $userId = $parent->user->id;
                    $userDeleted = $parent->user->delete();
                    Log::info('User du parent supprimé', ['user_id' => $userId, 'success' => (bool) $userDeleted]);
                } else {
                    Log::warning('Parent orphelin sans user lié', ['parent_id' => $parent->id]);
                }
            }
        }

        DB::commit();
        Log::info('=== ENFANT SUPPRIMÉ + PARENTS ORPHELINS TRAITÉS ===');

        return response()->json([
            'success' => true,
            'message' => 'Enfant supprimé avec succès'
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('Erreur suppression enfant', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la suppression de l\'enfant',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /**
     * 📊 Statistiques des enfants
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            $stats = [
                'total_enfants' => Enfant::count(),
                
                // Répartition par sexe
                'repartition_sexe' => [
                    'garcon' => Enfant::where('sexe', 'garçon')->count(),
                    'fille' => Enfant::where('sexe', 'fille')->count(),
                ],
                
                // Répartition par classe
                'repartition_classes' => Classe::withCount('enfants')->get()->map(function($classe) {
                    return [
                        'classe' => $classe->niveau,
                        'nombre_enfants' => $classe->enfants_count
                    ];
                }),
                
                // Répartition par âge
                'repartition_age' => $this->getAgeDistribution(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur statistiques enfants: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    /**
     * 🏥 Mettre à jour les informations médicales
     */
    public function updateMedicalInfo(Request $request, Enfant $enfant): JsonResponse
    {


        $validator = Validator::make($request->all(), [
            'allergies' => 'nullable|string',
            'remarques_medicales' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données médicales invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $enfant->update($request->only([
                'allergies', 
                'remarques_medicales'
            ]));

            Log::info('Informations médicales mises à jour', [
                'enfant_id' => $enfant->id,
                'admin_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Informations médicales mises à jour avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur mise à jour infos médicales: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des informations médicales'
            ], 500);
        }
    }

    /**
     * 📅 Calculer la répartition par âge
     */
    private function getAgeDistribution(): array
    {
        $enfants = Enfant::select('date_naissance')->get();
        $repartition = [
            '0-2 ans' => 0,
            '3-5 ans' => 0,
            '6-8 ans' => 0,
            '9-11 ans' => 0,
            '12+ ans' => 0
        ];

        foreach ($enfants as $enfant) {
            $age = now()->diffInYears($enfant->date_naissance);
            
            if ($age <= 2) {
                $repartition['0-2 ans']++;
            } elseif ($age <= 5) {
                $repartition['3-5 ans']++;
            } elseif ($age <= 8) {
                $repartition['6-8 ans']++;
            } elseif ($age <= 11) {
                $repartition['9-11 ans']++;
            } else {
                $repartition['12+ ans']++;
            }
        }

        return $repartition;
    }
}