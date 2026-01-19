<?php

namespace App\Http\Controllers\Admin;

use App\Models\Enfant;
use App\Http\Controllers\Controller;
use App\Models\ParentModel;
use App\Models\User;
use App\Services\ParentService;
use App\Http\Resources\ParentResource;
use App\Http\Requests\StoreParentRequest;
use App\Http\Requests\UpdateParentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
class ParentController extends Controller
{
    use AuthorizesRequests;
    protected $parentService;

    public function __construct(ParentService $parentService)
    {
        $this->parentService = $parentService;
    }

    /**
     * Afficher la liste des parents (Admin seulement)
     */
    public function index(Request $request): JsonResponse
    {

        try {
            $filters = $request->only([
                'statut',
                'search',
                'profession',
                'has_children',
                'sort_by',
                'sort_direction'
            ]);

            $query = $this->parentService->searchParents($filters);
            $perPage = $request->get('per_page', 10);
            $parents = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ParentResource::collection($parents),
                'pagination' => [
                    'current_page' => $parents->currentPage(),
                    'last_page' => $parents->lastPage(),
                    'per_page' => $parents->perPage(),
                    'total' => $parents->total(),
                ],
                'message' => 'Liste des parents récupérée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des parents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un parent spécifique (Admin et Parent lui-même)
     */

    public function show(ParentModel $parent): JsonResponse
    {
        try {
            // Vérifie les autorisations
            $this->authorize('view', $parent);

            $parent->load([
                'user:id,name,email,created_at', // Corrigé : nom → name, supprimé statut (inexistant)
                'enfants:id,nom,prenom,date_naissance,classe_id'
            ]);

            return response()->json([
                'success' => true,
                'data' => new ParentResource($parent),
                'message' => 'Parent trouvé avec succès'
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parent non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }


    /**
     * Créer un nouveau parent (Admin seulement)
     */
    public function store(Request $request): JsonResponse
    {
        $authenticatedUser = Auth::user();

        // Vérifier que l'utilisateur est admin
        if (!$authenticatedUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        // Validation des données
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'profession' => 'nullable|string|max:255',
            'telephone' => 'required|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'adresse' => 'nullable|string',
            'contact_urgence_nom' => 'nullable|string|max:255',
            'contact_urgence_telephone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'enfants' => 'nullable|array|min:1',
            'enfants.*' => 'integer|exists:enfant,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Vérifier que le rôle parent existe
            $parentRole = \Spatie\Permission\Models\Role::where('name', 'parent')->first();
            if (!$parentRole) {
                throw new \Exception('Le rôle parent n\'existe pas dans le système');
            }

            // Créer l'utilisateur
            $newUser = User::create([
                'name' => $request->prenom . ' ' . $request->nom,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'statut' => 'actif',
                'role' => 'parent', // 👈 ajoute ceci
            ]);


            // Nettoyer tous les rôles existants et assigner uniquement parent
            $newUser->syncRoles(['parent']);

            // OU utiliser assignRole avec vérification
            // $newUser->assignRole('parent');

            // Vérifier que l'assignation a fonctionné
            if (!$newUser->hasRole('parent')) {
                throw new \Exception('Erreur lors de l\'assignation du rôle parent');
            }

            // Créer le parent
            $parent = ParentModel::create([
                'user_id' => $newUser->id,
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'profession' => $request->profession,
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'contact_urgence_nom' => $request->contact_urgence_nom,
                'contact_urgence_telephone' => $request->contact_urgence_telephone,
            ]);

            // Associer les enfants si fournis
            if ($request->has('enfants') && is_array($request->enfants)) {
                $parent->enfants()->attach($request->enfants);
            }

            $parent->load(['user', 'enfants']);

            DB::commit();

            // Debug: vérifier le rôle après commit
            \Log::info('Rôles assignés à l\'utilisateur ' . $newUser->id . ': ' . $newUser->getRoleNames());

            return response()->json([
                'success' => true,
                'data' => new ParentResource($parent),
                'message' => 'Parent créé avec succès'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création parent: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Mettre à jour un parent (Admin et Parent lui-même)
     */
    public function update(Request $request, ParentModel $parent): JsonResponse
    {
        $authenticatedUser = Auth::user();
        $isAdmin = $authenticatedUser->hasRole('admin');
        $userParent = $authenticatedUser->parent;
        $isOwnProfile = $userParent && $userParent->id === $parent->id;

        if (!$isAdmin && !$isOwnProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        // 🔒 Validation
        $rules = [
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'profession' => 'nullable|string|max:255',
            'telephone' => 'sometimes|string|max:20',
            'adresse' => 'nullable|string',
            'contact_urgence_nom' => 'nullable|string|max:255',
            'contact_urgence_telephone' => 'nullable|string|max:20',
            // Validation pour l'objet user imbriqué
            'user.nom' => 'sometimes|string|max:255',
            'user.prenom' => 'sometimes|string|max:255',
            'user.email' => 'sometimes|email|unique:users,email,' . $parent->user_id,
            'user.password' => 'nullable|string|min:8',
            'user.role' => 'sometimes|in:educateur,admin,parent',
        ];

        if ($isAdmin) {
            $rules['email'] = 'sometimes|email|unique:users,email,' . $parent->user_id;
            $rules['password'] = 'nullable|string|min:8';
            $rules['role'] = 'sometimes|in:educateur,admin,parent';
            $rules['enfants'] = 'nullable|array';
            $rules['enfants.*'] = 'exists:enfant,id';
        }

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

            // 🔍 DEBUG: Données avant mise à jour
            \Log::info('=== DEBUT DEBUG UPDATE PARENT ===');
            \Log::info('Request data', $request->all()); // ✅ Correction ici
            \Log::info('Parent ID: ' . $parent->id); // ✅ Concaténation string
            \Log::info('User ID: ' . $parent->user_id); // ✅ Concaténation string
            \Log::info('User avant mise à jour', $parent->user->toArray());

            // 🔁 1. Mise à jour de la table users
            $userData = [];

            // 🔍 Récupérer les données user (soit directement, soit dans l'objet user)
            $userInput = $request->input('user', []);
            $hasUserData = !empty($userInput);

            \Log::info('User input trouvé', $userInput);
            \Log::info('Has user data: ' . ($hasUserData ? 'Oui' : 'Non'));

            // Email et password (champs existants dans users)
            if ($isAdmin && ($request->filled('email') || isset($userInput['email']))) {
                $email = $userInput['email'] ?? $request->email;
                $userData['email'] = $email;
                \Log::info('Email à mettre à jour: ' . $email);
            }

            if ($request->filled('password') || isset($userInput['password'])) {
                $password = $userInput['password'] ?? $request->password;
                $userData['password'] = Hash::make($password);
                \Log::info('Password sera mis à jour (hashé)');
            }

            if ($isAdmin && ($request->filled('role') || isset($userInput['role']))) {
                $role = $userInput['role'] ?? $request->role;
                $userData['role'] = $role;
                \Log::info('Role à mettre à jour: ' . $role);
            }

            // Mise à jour du champ name dans users
            $nom = $userInput['nom'] ?? $request->nom ?? '';
            $prenom = $userInput['prenom'] ?? $request->prenom ?? '';

            if (!empty($nom) || !empty($prenom)) {
                $userData['name'] = trim($prenom . ' ' . $nom);
                \Log::info('Name à mettre à jour: ' . $userData['name']);
            }

            \Log::info('Données users à mettre à jour', $userData);

            // Appliquer les modifications à la table users
            if (!empty($userData)) {
                // Utiliser directement l'objet User
                $userModel = User::find($parent->user_id);
                \Log::info('User trouvé: ' . ($userModel ? 'Oui' : 'Non')); // ✅ Correction

                if ($userModel) {
                    $result = $userModel->update($userData);
                    \Log::info('Résultat update users: ' . ($result ? 'Succès' : 'Échec')); // ✅ Correction

                    // Synchroniser les rôles Spatie si nécessaire
                    if (isset($userData['role'])) {
                        $userModel->syncRoles([$userData['role']]);
                        \Log::info('Rôles synchronisés: ' . $userData['role']); // ✅ Correction
                    }
                }
            } else {
                \Log::info('Aucune donnée users à mettre à jour');
            }

            // 🔍 DEBUG: Vérifier après mise à jour users
            $parent->user->refresh();
            \Log::info('User après refresh', $parent->user->toArray());

            // 🔁 2. Mise à jour de la table parents
            $parentData = [];

            if ($request->has('profession'))
                $parentData['profession'] = $request->profession;
            if ($request->filled('telephone'))
                $parentData['telephone'] = $request->telephone;
            if ($request->has('adresse'))
                $parentData['adresse'] = $request->adresse;
            if ($request->has('contact_urgence_nom'))
                $parentData['contact_urgence_nom'] = $request->contact_urgence_nom;
            if ($request->has('contact_urgence_telephone'))
                $parentData['contact_urgence_telephone'] = $request->contact_urgence_telephone;

            \Log::info('Données parent à mettre à jour', $parentData);

            if (!empty($parentData)) {
                $resultParent = $parent->update($parentData);
                \Log::info('Résultat update parent: ' . ($resultParent ? 'Succès' : 'Échec')); // ✅ Correction
            }

            // 🔁 3. Synchronisation des enfants si admin
            if ($isAdmin && $request->has('enfants') && is_array($request->enfants)) {
                $parent->enfants()->sync($request->enfants);
                \Log::info('Enfants synchronisés', $request->enfants);
            }

            DB::commit();
            \Log::info('Transaction commitée avec succès');

            // Forcer le rechargement des données
            $parent->refresh();
            $parent->load(['user', 'enfants']);

            \Log::info('=== FIN DEBUG UPDATE PARENT ===');

            return response()->json([
                'success' => true,
                'data' => new ParentResource($parent),
                'message' => 'Parent mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('=== ERREUR UPDATE PARENT ===');
            \Log::error('Message: ' . $e->getMessage()); // ✅ Correction
            \Log::error('Trace: ' . $e->getTraceAsString()); // ✅ Correction

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Supprimer un parent (Admin seulement)
     */
    public function destroy(ParentModel $parent): JsonResponse
    {
        $user = Auth::user();

        // Vérifier que l'utilisateur est admin
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // 🔍 DEBUG: Informations avant suppression
            \Log::info('=== DEBUT DEBUG SUPPRESSION PARENT ===');
            \Log::info('Parent ID: ' . $parent->id);
            \Log::info('User ID: ' . $parent->user_id);
            \Log::info('User avant suppression', $parent->user->toArray());

            // 1. Récupérer l'utilisateur AVANT de supprimer le parent
            $userToUpdate = User::find($parent->user_id);

            if (!$userToUpdate) {
                throw new \Exception('Utilisateur associé non trouvé');
            }

            // 2. Détacher les enfants
            $enfantsDetaches = $parent->enfants()->count();
            $parent->enfants()->detach();
            \Log::info('Enfants détachés: ' . $enfantsDetaches);

            // 3. Supprimer le parent de la table parents
            $parentDeleted = $parent->delete();
            \Log::info('Parent supprimé: ' . ($parentDeleted ? 'Succès' : 'Échec'));

            // 4. Gérer l'utilisateur associé
            // Option A: Supprimer complètement l'utilisateur
            $userDeleted = $userToUpdate->delete();
            \Log::info('User supprimé: ' . ($userDeleted ? 'Succès' : 'Échec'));

            // Option B: Si vous préférez désactiver (décommentez et ajustez le nom du champ)
            // $userToUpdate->update([
            //     'email_verified_at' => null,  // Désactiver la vérification email
            //     'password' => null,           // Vider le mot de passe
            //     // 'is_active' => false,      // Si vous avez un champ is_active
            //     // 'deleted_at' => now(),     // Si vous utilisez SoftDeletes
            // ]);
            // \Log::info('User désactivé');

            DB::commit();
            \Log::info('Transaction commitée avec succès');
            \Log::info('=== FIN DEBUG SUPPRESSION PARENT ===');

            return response()->json([
                'success' => true,
                'message' => 'Parent et utilisateur associé supprimés avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('=== ERREUR SUPPRESSION PARENT ===');
            \Log::error('Message: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Changer le statut d'un parent (Admin seulement)
     */
    public function changeStatus(Request $request, ParentModel $parent): JsonResponse
    {
        $user = Auth::user();

        // Vérifier que l'utilisateur est admin
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'statut' => 'required|in:actif,inactif,suspendu'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Statut invalide',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $parent->user->update(['statut' => $request->statut]);

            return response()->json([
                'success' => true,
                'message' => 'Statut du parent mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des parents (Admin seulement)
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();

        // Vérifier que l'utilisateur est admin
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            $stats = [
                'total_parents' => ParentModel::count(),
                'parents_actifs' => ParentModel::whereHas('user', function ($q) {
                    $q->where('statut', 'actif');
                })->count(),
                'parents_inactifs' => ParentModel::whereHas('user', function ($q) {
                    $q->where('statut', 'inactif');
                })->count(),
                'parents_suspendus' => ParentModel::whereHas('user', function ($q) {
                    $q->where('statut', 'suspendu');
                })->count(),
                'parents_avec_enfants' => ParentModel::has('enfants')->count(),
                'parents_sans_enfants' => ParentModel::doesntHave('enfants')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistiques récupérées avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Profil du parent connecté
     */
    public function profile(): JsonResponse
    {
        try {
            $user = Auth::user();
            $parent = $user->parent;

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil parent non trouvé'
                ], 404);
            }

            $parent->load(['user', 'enfants.classe']);

            return response()->json([
                'success' => true,
                'data' => new ParentResource($parent),
                'message' => 'Profil récupéré avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil du parent connecté
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'profession' => 'nullable|string|max:255',
            'telephone' => 'sometimes|string|max:20',
            'adresse' => 'nullable|string',
            'contact_urgence_nom' => 'nullable|string|max:255',
            'contact_urgence_telephone' => 'nullable|string|max:20',
            'current_password' => 'required_with:password|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $parent = $user->parent;

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil parent non trouvé'
                ], 404);
            }

            DB::beginTransaction();

            // Vérifier le mot de passe actuel si un nouveau est fourni
            if ($request->has('password') && !empty($request->password)) {
                if (!$request->has('current_password') || !Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Mot de passe actuel incorrect'
                    ], 422);
                }
            }

            // Mettre à jour les données utilisateur
            $userData = $request->only(['nom', 'prenom']);
            if ($request->has('password') && !empty($request->password)) {
                $userData['password'] = Hash::make($request->password);
            }

            if (!empty($userData)) {
                $user->update($userData);
            }

            // Mettre à jour les données parent
            $parentData = $request->only([
                'profession',
                'telephone',
                'adresse',
                'contact_urgence_nom',
                'contact_urgence_telephone'
            ]);

            if (!empty($parentData)) {
                $parent->update($parentData);
            }

            $parent->load(['user', 'enfants']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new ParentResource($parent),
                'message' => 'Profil mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Version debug - Récupérer les notes d'un enfant pour le parent connecté
     */


    public function getNoteEnfant($enfantId): JsonResponse
    {
        try {
            // 1) Auth
            $user = Auth::user();
            Log::info('User connecté', ['user_id' => $user?->id, 'email' => $user?->email]);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié',
                    'debug' => 'Auth::user() retourne null'
                ], 401);
            }

            // 2) Relation parent depuis le user
            $parent = $user->parent; // doit exister: User->parent():hasOne(Parent::class)
            Log::info('Parent trouvé', ['parent_id' => $parent?->id]);

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun parent connecté',
                    'debug' => [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'parent_relation' => 'null'
                    ]
                ], 403);
            }

            // 3) Enfants liés
            $enfants = $parent->enfants; // Parent->enfants():belongsToMany(Enfant::class)
            Log::info('Enfants du parent', ['count' => $enfants->count(), 'ids' => $enfants->pluck('id')->all()]);

            // 4) Vérifier que l'enfant demandé est bien lié à ce parent
            $enfant = $parent->enfants()->find($enfantId);
            Log::info('Enfant recherché', ['recherche' => $enfantId, 'trouvé' => $enfant?->id]);

            if (!$enfant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enfant non trouvé ou non lié à ce parent',
                    'debug' => [
                        'enfant_id_recherche' => $enfantId,
                        'parent_id' => $parent->id,
                        'enfants_disponibles' => $enfants->map(fn($e) => [
                            'id' => $e->id,
                            'nom' => $e->nom,
                            'prenom' => $e->prenom,
                        ])->values(),
                    ]
                ], 404);
            }

            // 5) Charger les notes + relations (⚠️ on passe par educateur.user pour nom/prenom)
            // SuivieNote relations attendues:
            //   matiere(): belongsTo(Matiere::class)
            //   educateur(): belongsTo(Educateur::class)  (Educateur a user_id)
            // Educateur relation attendue:
            //   user(): belongsTo(User::class)
            $notesWithRelations = $enfant->suivieNotes()
                ->with([
                    'matiere:id,nom',
                    'educateur:id,user_id',
                    'educateur.user:id,name',   // <= name au lieu de nom/prenom
                ])
                ->orderBy('date_evaluation', 'desc')
                ->get();

            $formattedNotes = $notesWithRelations->map(function ($note) {
                // date
                $date = $note->date_evaluation
                    ? (method_exists($note->date_evaluation, 'format')
                        ? $note->date_evaluation->format('Y-m-d')
                        : Carbon::parse($note->date_evaluation)->format('Y-m-d'))
                    : null;

                // nom/prenom dérivés de "name" (si tu veux les champs séparés)
                $fullName = optional($note->educateur?->user)->name;
                $prenom = null;
                $nom = null;
                if ($fullName) {
                    $parts = preg_split('/\s+/', trim($fullName), 2);
                    $prenom = $parts[0] ?? null;
                    $nom = $parts[1] ?? null;
                }

                return [
                    'id' => $note->id,
                    'note' => $note->note,
                    'type_evaluation' => $note->type_evaluation,
                    'date_evaluation' => $date,
                    'trimestre' => $note->trimestre,
                    'commentaire' => $note->commentaire,
                    'mention' => $note->mention,
                    'matiere' => $note->matiere ? [
                        'id' => $note->matiere->id,
                        'nom' => $note->matiere->nom,
                    ] : null,
                    'educateur' => $note->educateur ? [
                        'id' => $note->educateur->id,
                        'nom_complet' => $fullName,  // depuis users.name
                        'nom' => $nom,       // optionnel : partie après le 1er espace
                        'prenom' => $prenom,    // optionnel : 1er mot
                    ] : null,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $formattedNotes,
                'enfant' => ['id' => $enfant->id, 'nom' => $enfant->nom, 'prenom' => $enfant->prenom],
                'total_notes' => $notesWithRelations->count(),
                'message' => "Notes de l'enfant récupérées avec succès",
                'debug' => [
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'enfant_id' => $enfant->id,
                    'notes_count' => $notesWithRelations->count()
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('Erreur getNoteEnfant', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des notes',
                'error' => $e->getMessage(),
                'debug' => ['line' => $e->getLine(), 'file' => $e->getFile()]
            ], 500);
        }
    }

}