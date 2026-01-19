<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Educateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClasseController extends Controller
{
    /**
     * GET /api/admin/classes
     * Liste paginée avec filtres et recherche
     */
    public function index(Request $request)
    {
        try {
            $query = Classe::with(['educateurs.user', 'enfants']);

            // Filtrage par niveau
            if ($request->filled('niveau')) {
                $query->where('niveau', $request->niveau);
            }

            // Filtrage par capacité
            if ($request->filled('capacite_min')) {
                $query->where('capacite_max', '>=', $request->capacite_min);
            }

            if ($request->filled('capacite_max')) {
                $query->where('capacite_max', '<=', $request->capacite_max);
            }

            // Recherche par nom
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nom', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Tri
            $sortBy = $request->get('sort_by', 'nom');
            $sortOrder = $request->get('sort_order', 'asc');
            
            if (in_array($sortBy, ['nom', 'niveau', 'capacite_max', 'created_at'])) {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $classes = $query->paginate($perPage);

            // Ajouter des informations calculées
            $classes->getCollection()->transform(function ($classe) {
                $classe->nombre_enfants = $classe->enfants->count();
                $classe->places_disponibles = $classe->capacite_max - $classe->nombre_enfants;
                $classe->est_complete = $classe->nombre_enfants >= $classe->capacite_max;
                $classe->nombre_educateurs = $classe->educateurs->count();
                unset($classe->enfants); // Pas besoin des détails des enfants dans la liste
                return $classe;
            });

            return response()->json([
                'success' => true,
                'data' => $classes,
                'filters' => [
                    'niveau' => $request->niveau,
                    'capacite_min' => $request->capacite_min,
                    'capacite_max' => $request->capacite_max,
                    'search' => $request->search,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/admin/classes
     * Créer une nouvelle classe
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nom' => 'required|string|max:255|unique:classe,nom',
                'niveau' => 'required|string|max:100',
                'capacite_max' => 'required|integer|min:1|max:50',
                'description' => 'nullable|string|max:1000'
            ], [
                'nom.required' => 'Le nom de la classe est obligatoire',
                'nom.unique' => 'Une classe avec ce nom existe déjà',
                'niveau.required' => 'Le niveau est obligatoire',
                'capacite_max.required' => 'La capacité maximale est obligatoire',
                'capacite_max.min' => 'La capacité doit être d\'au moins 1',
                'capacite_max.max' => 'La capacité ne peut pas dépasser 50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // CORRECTION: Utiliser $validator->validated() au lieu de $request->validated()
            $classe = Classe::create($validator->validated());

            $classe->load(['educateurs.user']);
            $classe->nombre_enfants = 0;
            $classe->places_disponibles = $classe->capacite_max;
            $classe->est_complete = false;
            $classe->nombre_educateurs = 0;

            return response()->json([
                'success' => true,
                'message' => 'Classe créée avec succès',
                'data' => $classe
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
     * GET /api/admin/classes/{id}
     * Détails d'une classe spécifique
     */
    public function show($id)
    {
        try {
            $classe = Classe::with([
                'educateurs.user:id,name,email',
                'enfants:id,nom,prenom,date_naissance,classe_id',
                'matieres:id,nom'
            ])->findOrFail($id);

            // Ajouter des informations calculées
            $classe->nombre_enfants = $classe->enfants->count();
            $classe->places_disponibles = $classe->capacite_max - $classe->nombre_enfants;
            $classe->est_complete = $classe->nombre_enfants >= $classe->capacite_max;
            $classe->nombre_educateurs = $classe->educateurs->count();
            $classe->nombre_matieres = $classe->matieres->count();

            // Statistiques d'âge des enfants
            if ($classe->enfants->count() > 0) {
                $ages = $classe->enfants->map(function($enfant) {
                    return now()->diffInYears($enfant->date_naissance);
                });
                
                $classe->age_moyen = round($ages->avg(), 1);
                $classe->age_min = $ages->min();
                $classe->age_max = $ages->max();
            }

            return response()->json([
                'success' => true,
                'data' => $classe
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classe non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * PUT /api/admin/classes/{id}
     * Modifier une classe existante
     */


public function update(Request $request, $id)
{
    $classe = Classe::findOrFail($id);

    $rules = [
        'nom' => ['sometimes','string','max:255', Rule::unique('classe','nom')->ignore($id)],
        'niveau' => ['sometimes','string','max:100'],
        'capacite_max' => ['sometimes','integer','min:1','max:50'],
        'description' => ['sometimes','nullable','string','max:1000'],
    ];

    $messages = [
        'nom.unique' => 'Une classe avec ce nom existe déjà',
        'capacite_max.max' => 'La capacité ne peut pas dépasser 50',
        // ... (tes autres messages)
    ];

    $validated = Validator::make($request->all(), $rules, $messages)->validate();

    // Vérifier la capacité uniquement si on la modifie
    if (array_key_exists('capacite_max', $validated)) {
        $nb = $classe->enfants()->count();
        if ($validated['capacite_max'] < $nb) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de réduire la capacité à {$validated['capacite_max']}. "
                           . "La classe a actuellement {$nb} enfants inscrits."
            ], 422);
        }
    }

    $classe->fill($validated)->save();
    $classe->load('educateurs.user');

    // Infos calculées (optionnel)
    $nbEnfants = $classe->enfants()->count();
    $classe->nombre_enfants     = $nbEnfants;
    $classe->places_disponibles = $classe->capacite_max - $nbEnfants;
    $classe->est_complete       = $nbEnfants >= $classe->capacite_max;
    $classe->nombre_educateurs  = $classe->educateurs->count();

    return response()->json([
        'success' => true,
        'message' => 'Classe mise à jour avec succès',
        'data'    => $classe
    ]);
}

    /**
     * DELETE /api/admin/classes/{id}
     * Supprimer une classe
     */
    public function destroy($id)
    {
        try {
            $classe = Classe::findOrFail($id);

            // Vérifier si la classe a des enfants inscrits
            $nombreEnfants = $classe->enfants()->count();
            if ($nombreEnfants > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de supprimer cette classe. Elle contient {$nombreEnfants} enfant(s) inscrit(s).",
                    'can_delete' => false,
                    'reason' => 'has_children'
                ], 422);
            }

            DB::beginTransaction();

            // Détacher les éducateurs
            $classe->educateurs()->detach();
            
            // Détacher les matières
            $classe->matieres()->detach();

            // Supprimer la classe
            $classe->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Classe supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la classe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/list/simple
     * Liste simple pour les sélecteurs (dropdown)
     */
    public function list()
    {
        try {
            $classes = Classe::select('id', 'nom', 'niveau', 'capacite_max')
                ->with(['enfants:id,classe_id'])
                ->orderBy('niveau')
                ->orderBy('nom')
                ->get();

            $classes->transform(function ($classe) {
                $classe->nombre_enfants = $classe->enfants->count();
                $classe->places_disponibles = $classe->capacite_max - $classe->nombre_enfants;
                $classe->est_complete = $classe->nombre_enfants >= $classe->capacite_max;
                unset($classe->enfants);
                return $classe;
            });

            return response()->json([
                'success' => true,
                'data' => $classes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la liste des classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/statistics/all
     * Statistiques complètes des classes
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_classes' => Classe::count(),
                'total_enfants' => DB::table('enfant')->whereNotNull('classe_id')->count(),
                'total_educateurs' => DB::table('educateur_classe')->distinct('educateur_id')->count('educateur_id'),
                'capacite_totale' => Classe::sum('capacite_max'),
                'places_disponibles' => Classe::sum('capacite_max') - DB::table('enfant')->whereNotNull('classe_id')->count(),
                'taux_occupation' => 0,
                'repartition_par_niveau' => [],
                'classes_completes' => 0,
                'classes_vides' => 0,
                'moyenne_enfants_par_classe' => 0
            ];

            if ($stats['capacite_totale'] > 0) {
                $stats['taux_occupation'] = round(($stats['total_enfants'] / $stats['capacite_totale']) * 100, 1);
            }

            if ($stats['total_classes'] > 0) {
                $stats['moyenne_enfants_par_classe'] = round($stats['total_enfants'] / $stats['total_classes'], 1);
            }

            // Répartition par niveau
            $niveaux = Classe::select('niveau', DB::raw('count(*) as nombre_classes'), DB::raw('sum(capacite_max) as capacite_totale'))
                ->groupBy('niveau')
                ->orderBy('niveau')
                ->get();

            foreach ($niveaux as $niveau) {
                $nombreEnfants = DB::table('enfant')
                    ->join('classe', 'enfant.classe_id', '=', 'classe.id')
                    ->where('classe.niveau', $niveau->niveau)
                    ->count();

                $stats['repartition_par_niveau'][] = [
                    'niveau' => $niveau->niveau,
                    'nombre_classes' => $niveau->nombre_classes,
                    'capacite_totale' => $niveau->capacite_totale,
                    'nombre_enfants' => $nombreEnfants,
                    'taux_occupation' => $niveau->capacite_totale > 0 ? round(($nombreEnfants / $niveau->capacite_totale) * 100, 1) : 0
                ];
            }

            // Classes complètes et vides
            $classesAvecStats = Classe::withCount('enfants')->get();
            $stats['classes_completes'] = $classesAvecStats->where('enfants_count', '>=', function($classe) { return $classe->capacite_max; })->count();
            $stats['classes_vides'] = $classesAvecStats->where('enfants_count', 0)->count();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/with/educateurs
     * Classes avec leurs éducateurs
     */
  public function withEducateurs()
{
    try {
        // Eager-load propre avec colonnes qualifiées
        $classes = Classe::query()
            ->with([
                'educateurs' => fn ($q) => $q->select('educateurs.id', 'educateurs.user_id'),
                'educateurs.user' => fn ($q) => $q->select('users.id', 'users.name'),
            ])
            ->select('classe.id', 'classe.nom')   // seulement id + nom
            ->orderBy('classe.nom')
            ->get();

        // Payload minimal: { id, name, educateurs: [{id, name}] }
        $data = $classes->map(function ($c) {
            return [
                'id'   => $c->id,
                'name' => $c->nom,
                'educateurs' => $c->educateurs->map(function ($e) {
                    return [
                        'id'   => $e->id,
                        'name' => optional($e->user)->name, // depuis users.name
                    ];
                })->values(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des classes avec éducateurs',
            'error'   => $e->getMessage(),
        ], 500);
    }
}



    /**
     * POST /api/admin/classes/search
     * Recherche avancée
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'term' => 'required|string|min:2',
                'filters' => 'array',
                'filters.niveau' => 'string',
                'filters.has_educateurs' => 'boolean',
                'filters.est_complete' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $term = $request->term;
            $filters = $request->get('filters', []);

            $query = Classe::with(['educateurs.user:id,name', 'enfants:id,classe_id'])
                ->where(function($q) use ($term) {
                    $q->where('nom', 'LIKE', "%{$term}%")
                      ->orWhere('niveau', 'LIKE', "%{$term}%")
                      ->orWhere('description', 'LIKE', "%{$term}%");
                });

            // Appliquer les filtres
            if (isset($filters['niveau'])) {
                $query->where('niveau', $filters['niveau']);
            }

            if (isset($filters['has_educateurs']) && $filters['has_educateurs']) {
                $query->has('educateurs');
            }

            $classes = $query->get();

            // Appliquer le filtre de classes complètes après récupération
            if (isset($filters['est_complete'])) {
                $classes = $classes->filter(function($classe) use ($filters) {
                    $estComplete = $classe->enfants->count() >= $classe->capacite_max;
                    return $estComplete === $filters['est_complete'];
                });
            }

            // Ajouter des informations calculées
            $classes->transform(function ($classe) {
                $classe->nombre_enfants = $classe->enfants->count();
                $classe->places_disponibles = $classe->capacite_max - $classe->nombre_enfants;
                $classe->est_complete = $classe->nombre_enfants >= $classe->capacite_max;
                $classe->nombre_educateurs = $classe->educateurs->count();
                unset($classe->enfants);
                return $classe;
            });

            return response()->json([
                'success' => true,
                'data' => $classes->values(),
                'search_term' => $term,
                'filters_applied' => $filters,
                'total_results' => $classes->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/admin/classes/{id}/duplicate
     * Dupliquer une classe
     */
    public function duplicate($id)
    {
        try {
            $classeOriginale = Classe::findOrFail($id);

            DB::beginTransaction();

            $nouvelleClasse = $classeOriginale->replicate();
            $nouvelleClasse->nom = $classeOriginale->nom . ' (Copie)';
            $nouvelleClasse->save();

            // Optionnel : copier les relations avec les matières
            if ($classeOriginale->matieres()->exists()) {
                $matieres = $classeOriginale->matieres()->withPivot(['heures_par_semaine', 'objectifs_specifiques'])->get();
                foreach ($matieres as $matiere) {
                    $nouvelleClasse->matieres()->attach($matiere->id, [
                        'heures_par_semaine' => $matiere->pivot->heures_par_semaine,
                        'objectifs_specifiques' => $matiere->pivot->objectifs_specifiques
                    ]);
                }
            }

            DB::commit();

            $nouvelleClasse->load(['educateurs.user', 'matieres']);
            $nouvelleClasse->nombre_enfants = 0;
            $nouvelleClasse->places_disponibles = $nouvelleClasse->capacite_max;
            $nouvelleClasse->est_complete = false;
            $nouvelleClasse->nombre_educateurs = 0;

            return response()->json([
                'success' => true,
                'message' => 'Classe dupliquée avec succès',
                'data' => $nouvelleClasse
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication de la classe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/admin/classes/check-nom
     * Vérifier la disponibilité d'un nom de classe
     */
    public function checkNomDisponibilite(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nom' => 'required|string|max:255',
                'exclude_id' => 'nullable|integer|exists:classe,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Classe::where('nom', $request->nom);
            
            if ($request->filled('exclude_id')) {
                $query->where('id', '!=', $request->exclude_id);
            }

            $existe = $query->exists();

            return response()->json([
                'success' => true,
                'available' => !$existe,
                'nom' => $request->nom,
                'message' => $existe ? 'Ce nom est déjà utilisé' : 'Ce nom est disponible'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/{id}/rapport
     * Rapport détaillé d'une classe
     */
    public function rapport($id)
    {
        try {
            $classe = Classe::with([
                'educateurs.user:id,name,email,telephone',
                'enfants:id,nom,prenom,date_naissance,classe_id,allergies',
                'matieres:id,nom,description'
            ])->findOrFail($id);

            $rapport = [
                'classe' => $classe,
                'resume' => [
                    'nombre_enfants' => $classe->enfants->count(),
                    'nombre_educateurs' => $classe->educateurs->count(),
                    'nombre_matieres' => $classe->matieres->count(),
                    'places_disponibles' => $classe->capacite_max - $classe->enfants->count(),
                    'taux_occupation' => $classe->capacite_max > 0 ? round(($classe->enfants->count() / $classe->capacite_max) * 100, 1) : 0
                ],
                'enfants_par_age' => [],
                'educateurs_actifs' => $classe->educateurs->count(),
                'generated_at' => now()->format('Y-m-d H:i:s')
            ];

            // Grouper les enfants par âge
            if ($classe->enfants->count() > 0) {
                $enfantsParAge = $classe->enfants->groupBy(function($enfant) {
                    return now()->diffInYears($enfant->date_naissance);
                });

                foreach ($enfantsParAge as $age => $enfants) {
                    $rapport['enfants_par_age'][] = [
                        'age' => $age,
                        'nombre' => $enfants->count(),
                        'noms' => $enfants->pluck('prenom', 'nom')->map(function($prenom, $nom) {
                            return "$prenom $nom";
                        })->values()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rapport
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/admin/classes/{id}/archiver
     * Archiver une classe (soft delete ou statut)
     */
    public function archiver($id)
    {
        try {
            $classe = Classe::findOrFail($id);

            // Vérifier s'il y a des enfants
            $nombreEnfants = $classe->enfants()->count();
            if ($nombreEnfants > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible d'archiver cette classe. Elle contient {$nombreEnfants} enfant(s) inscrit(s)."
                ], 422);
            }

            // Si vous avez un champ 'archived_at' ou 'status'
            // $classe->update(['archived_at' => now()]);
            // Pour cet exemple, on utilise soft delete
            $classe->delete();

            return response()->json([
                'success' => true,
                'message' => 'Classe archivée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'archivage de la classe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/niveaux/disponibles
     * Liste des niveaux disponibles
     */
    public function niveauxDisponibles()
    {
        try {
            $niveaux = Classe::select('niveau')
                ->distinct()
                ->orderBy('niveau')
                ->pluck('niveau');

            return response()->json([
                'success' => true,
                'data' => $niveaux
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des niveaux',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/niveau/{niveau}
     * Classes par niveau spécifique
     */
    public function classesByNiveau($niveau)
    {
        try {

            $classes = Classe::with(['educateurs.user:id,name', 'enfants:id,classe_id'])
                ->where('niveau', $niveau)
                ->orderBy('nom')
                ->get();

            $classes->transform(function ($classe) {
                $classe->nombre_enfants = $classe->enfants->count();
                $classe->places_disponibles = $classe->capacite_max - $classe->nombre_enfants;
                $classe->est_complete = $classe->nombre_enfants >= $classe->capacite_max;
                $classe->nombre_educateurs = $classe->educateurs->count();
                unset($classe->enfants);
                return $classe;
            });

            return response()->json([
                'success' => true,
                'data' => $classes,
                'niveau' => $niveau,
                'total' => $classes->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des classes par niveau',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/disponibles/affectation
     * Classes disponibles pour affectation (non complètes)
     */
    public function disponiblesAffectation()
    {
        try {
            $classes = Classe::with(['enfants:id,classe_id'])
                ->select('id', 'nom', 'niveau', 'capacite_max')
                ->get()
                ->filter(function($classe) {
                    return $classe->enfants->count() < $classe->capacite_max;
                })
                ->map(function($classe) {
                    $nombreEnfants = $classe->enfants->count();
                    return [
                        'id' => $classe->id,
                        'nom' => $classe->nom,
                        'niveau' => $classe->niveau,
                        'capacite_max' => $classe->capacite_max,
                        'nombre_enfants' => $nombreEnfants,
                        'places_disponibles' => $classe->capacite_max - $nombreEnfants
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $classes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des classes disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/{id}/can-delete
     * Vérifier si une classe peut être supprimée
     */
    public function canDelete($id)
    {
        try {
            $classe = Classe::findOrFail($id);
            
            $nombreEnfants = $classe->enfants()->count();
            $canDelete = $nombreEnfants === 0;
            
            $response = [
                'success' => true,
                'can_delete' => $canDelete,
                'classe_id' => $id,
                'classe_nom' => $classe->nom,
                'nombre_enfants' => $nombreEnfants
            ];

            if (!$canDelete) {
                $response['message'] = "Cette classe ne peut pas être supprimée car elle contient {$nombreEnfants} enfant(s) inscrit(s).";
                $response['reason'] = 'has_children';
            } else {
                $response['message'] = 'Cette classe peut être supprimée.';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/classes/export/data
     * Export des données des classes
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'json'); // json, csv, excel

            $classes = Classe::with([
                'educateurs.user:id,name,email',
                'enfants:id,nom,prenom,date_naissance,classe_id',
                'matieres:id,nom'
            ])->get();

            $exportData = $classes->map(function($classe) {
                return [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'niveau' => $classe->niveau,
                    'capacite_max' => $classe->capacite_max,
                    'description' => $classe->description,
                    'nombre_enfants' => $classe->enfants->count(),
                    'places_disponibles' => $classe->capacite_max - $classe->enfants->count(),
                    'nombre_educateurs' => $classe->educateurs->count(),
                    'nombre_matieres' => $classe->matieres->count(),
                    'taux_occupation' => $classe->capacite_max > 0 ? round(($classe->enfants->count() / $classe->capacite_max) * 100, 1) : 0,
                    'educateurs' => $classe->educateurs->map(function($educateur) {
                        return $educateur->user->nom . ' ' . $educateur->user->prenom;
                    })->implode(', '),
                    'created_at' => $classe->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $classe->updated_at->format('Y-m-d H:i:s')
                ];
            });

            if ($format === 'csv') {
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="classes_export_' . date('Y-m-d') . '.csv"'
                ];

                $callback = function() use ($exportData) {
                    $file = fopen('php://output', 'w');
                    
                    // Headers CSV
                    fputcsv($file, [
                        'ID', 'Nom', 'Niveau', 'Capacité Max', 'Description',
                        'Nombre Enfants', 'Places Disponibles', 'Nombre Éducateurs',
                        'Nombre Matières', 'Taux Occupation (%)', 'Éducateurs',
                        'Date Création', 'Date Modification'
                    ]);

                    foreach ($exportData as $classe) {
                        fputcsv($file, [
                            $classe['id'],
                            $classe['nom'],
                            $classe['niveau'],
                            $classe['capacite_max'],
                            $classe['description'],
                            $classe['nombre_enfants'],
                            $classe['places_disponibles'],
                            $classe['nombre_educateurs'],
                            $classe['nombre_matieres'],
                            $classe['taux_occupation'],
                            $classe['educateurs'],
                            $classe['created_at'],
                            $classe['updated_at']
                        ]);
                    }

                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }

            // Format JSON par défaut
            return response()->json([
                'success' => true,
                'data' => $exportData,
                'export_date' => now()->format('Y-m-d H:i:s'),
                'total_classes' => $exportData->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}