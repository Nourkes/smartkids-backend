<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEducateurRequest;
use App\Http\Requests\Admin\UpdateEducateurRequest;
use App\Models\Educateur;
use App\Models\User;
use App\Models\Classe;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EducateurController extends Controller
{
    /**
     * Afficher la liste des éducateurs
     */
    public function index(): JsonResponse
    {
        try {
            $educateurs = Educateur::with(['user', 'classes', 'activites'])
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Liste des éducateurs récupérée avec succès',
                'data' => $educateurs->items(),
                'pagination' => [
                    'current_page' => $educateurs->currentPage(),
                    'last_page' => $educateurs->lastPage(),
                    'per_page' => $educateurs->perPage(),
                    'total' => $educateurs->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des éducateurs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des éducateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouvel éducateur
     */
    public function store(StoreEducateurRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. Créer l'utilisateur
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'educateur',
            ]);

            // 2. Créer le profil éducateur
            $educateur = Educateur::create([
                'user_id' => $user->id,
                'diplome' => $request->diplome,
                'date_embauche' => $request->date_embauche,
                'salaire' => $request->salaire,
            ]);

            // 3. Attacher les classes si spécifiées
            if ($request->has('classes') && !empty($request->classes)) {
                $educateur->classes()->attach($request->classes);
            }

            // 4. Charger les relations pour la réponse
            $educateur->load(['user', 'classes']);

            DB::commit();

            Log::info('Éducateur créé avec succès', [
                'educateur_id' => $educateur->id,
                'user_id' => $user->id,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Éducateur créé avec succès',
                'data' => [
                    'id' => $educateur->id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'diplome' => $educateur->diplome,
                    'date_embauche' => $educateur->date_embauche,
                    'salaire' => $educateur->salaire,
                    'classes' => $educateur->classes,
                    'created_at' => $educateur->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de la création de l\'éducateur', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'éducateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un éducateur spécifique
     */
    public function show(Educateur $educateur): JsonResponse
    {
        try {
            $educateur->load([
                'user',
                'classes.enfants',
                'activites',
                'presences' => function($query) {
                    $query->with('enfant')->latest()->limit(10);
                },
                'notesAttribuees' => function($query) {
                    $query->with(['enfant', 'matiere'])->latest()->limit(10);
                }
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Éducateur récupéré avec succès',
                'data' => $educateur
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de l\'éducateur', [
                'educateur_id' => $educateur->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'éducateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un éducateur
     */
    public function update(UpdateEducateurRequest $request, Educateur $educateur): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. Mettre à jour les données utilisateur si nécessaire
            $userData = [];
            if ($request->filled('name')) {
                $userData['name'] = $request->name;
            }
            if ($request->filled('email')) {
                $userData['email'] = $request->email;
            }
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            if (!empty($userData)) {
                $educateur->user->update($userData);
            }

            // 2. Mettre à jour les données de l'éducateur
            $educateurData = [];
            if ($request->filled('diplome')) {
                $educateurData['diplome'] = $request->diplome;
            }
            if ($request->filled('date_embauche')) {
                $educateurData['date_embauche'] = $request->date_embauche;
            }
            if ($request->filled('salaire')) {
                $educateurData['salaire'] = $request->salaire;
            }

            if (!empty($educateurData)) {
                $educateur->update($educateurData);
            }

            // 3. Synchroniser les classes si spécifiées
            if ($request->has('classes')) {
                $educateur->classes()->sync($request->classes);
            }

            // 4. Recharger les données pour la réponse
            $educateur->load(['user', 'classes']);

            DB::commit();

            Log::info('Éducateur mis à jour avec succès', [
                'educateur_id' => $educateur->id,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Éducateur mis à jour avec succès',
                'data' => $educateur
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de la mise à jour de l\'éducateur', [
                'educateur_id' => $educateur->id,
                'error' => $e->getMessage(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'éducateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un éducateur
     */
    public function destroy(Educateur $educateur): JsonResponse
    {
        DB::beginTransaction();

        try {
            $userId = $educateur->user_id;
            
            // Supprimer l'éducateur (les relations seront gérées par les contraintes de clés étrangères)
            $educateur->delete();
            
            // Supprimer l'utilisateur associé
            User::find($userId)?->delete();

            DB::commit();

            Log::info('Éducateur supprimé avec succès', [
                'educateur_id' => $educateur->id,
                'user_id' => $userId,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Éducateur supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de la suppression de l\'éducateur', [
                'educateur_id' => $educateur->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'éducateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des éducateurs
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_educateurs' => Educateur::count(),
                'educateurs_actifs' => Educateur::whereHas('user', function($query) {
                    $query->whereNotNull('email_verified_at');
                })->count(),
                'nouveaux_ce_mois' => Educateur::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'salaire_moyen' => Educateur::avg('salaire'),
                'repartition_par_diplome' => Educateur::selectRaw('diplome, COUNT(*) as count')
                    ->groupBy('diplome')
                    ->get()
                    ->pluck('count', 'diplome'),
                'classes_assignees' => DB::table('educateur_classe')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Statistiques des éducateurs récupérées avec succès',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques des éducateurs', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assigner des classes à un éducateur
     */
    public function assignClasses(Request $request, Educateur $educateur): JsonResponse
    {
        $request->validate([
            'classes' => 'required|array',
            'classes.*' => 'exists:classe,id'
        ]);

        try {
            $educateur->classes()->sync($request->classes);

            Log::info('Classes assignées à l\'éducateur', [
                'educateur_id' => $educateur->id,
                'classes' => $request->classes,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Classes assignées avec succès',
                'data' => $educateur->load('classes')
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'assignation des classes', [
                'educateur_id' => $educateur->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'assignation des classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}