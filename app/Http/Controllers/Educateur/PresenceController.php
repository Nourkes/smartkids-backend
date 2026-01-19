<?php

// ===========================
// 1. CONTROLLER PRÉSENCES ÉDUCATEUR
// app/Http/Controllers/Educateur/PresenceController.php
// ===========================

namespace App\Http\Controllers\Educateur;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Models\Enfant;
use App\Models\Classe;
use App\Models\Educateur;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PresenceController extends Controller
{
    /**
     * GET /api/educateur/classes
     * Récupérer toutes les classes assignées à l'éducateur connecté
     */
    public function getClassesEducateur(): JsonResponse
    {
        try {
            $user = Auth::user();
            $educateur = $user->educateur;

            if (!$educateur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil éducateur non trouvé'
                ], 404);
            }

            $classes = $educateur->classes()
                ->select('classe.id', 'classe.nom', 'classe.niveau', 'classe.capacite_max')
                ->withCount('enfants')
                ->orderBy('classe.niveau')
                ->orderBy('classe.nom')
                ->get();

            $classesFormatted = $classes->map(function ($classe) {
                return [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'niveau' => $classe->niveau,
                    'capacite_max' => $classe->capacite_max,
                    'nombre_enfants' => $classe->enfants_count
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $classesFormatted,
                'total' => $classesFormatted->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération classes éducateur: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des classes'
            ], 500);
        }
    }

    /**
     * GET /api/educateur/classes/{classeId}/presences
     * Récupérer les enfants d'une classe avec leurs présences pour une date donnée
     */
    public function getEnfantsClasse(Request $request, $classeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $educateur = $user->educateur;

            // Vérifier que l'éducateur a accès à cette classe
            if (!$educateur->classes()->where('classe.id', $classeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette classe'
                ], 403);
            }

            // Date par défaut : aujourd'hui
            $date = $request->get('date', now()->format('Y-m-d'));
            $datePresence = Carbon::parse($date);

            // Récupérer la classe avec ses enfants
            $classe = Classe::with(['enfants' => function($query) {
                $query->select('id', 'nom', 'prenom', 'classe_id', 'date_naissance')
                      ->orderBy('nom')
                      ->orderBy('prenom');
            }])->findOrFail($classeId);

            // Récupérer les présences existantes pour cette date et cet éducateur
            $presencesExistantes = Presence::where('date_presence', $datePresence)
                ->whereIn('enfant_id', $classe->enfants->pluck('id'))
                ->where('educateur_id', $educateur->id)
                ->get()
                ->keyBy('enfant_id');

            // Construire la liste des enfants avec leur statut de présence
            $enfantsAvecPresence = $classe->enfants->map(function ($enfant) use ($presencesExistantes, $datePresence) {
                $presence = $presencesExistantes->get($enfant->id);
                $age = $datePresence->diffInYears($enfant->date_naissance);
                
                return [
                    'id' => $enfant->id,
                    'nom' => $enfant->nom,
                    'prenom' => $enfant->prenom,
                    'nom_complet' => "{$enfant->prenom} {$enfant->nom}",
                    'age' => $age,
                    'statut' => $presence ? $presence->statut : 'absent',
                    'presence_id' => $presence ? $presence->id : null,
                    'deja_enregistre' => (bool) $presence,
                    'updated_at' => $presence ? $presence->updated_at->format('H:i') : null
                ];
            });

            // Calculs statistiques
            $totalEnfants = $enfantsAvecPresence->count();
            $presents = $enfantsAvecPresence->where('statut', 'present')->count();
            $absents = $enfantsAvecPresence->where('statut', 'absent')->count();
            $tauxPresence = $totalEnfants > 0 ? round(($presents / $totalEnfants) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'classe' => [
                        'id' => $classe->id,
                        'nom' => $classe->nom,
                        'niveau' => $classe->niveau,
                        'capacite_max' => $classe->capacite_max
                    ],
                    'date_presence' => $datePresence->format('Y-m-d'),
                    'date_libelle' => $datePresence->locale('fr')->isoFormat('dddd DD MMMM YYYY'),
                    'enfants' => $enfantsAvecPresence,
                    'resume' => [
                        'total_enfants' => $totalEnfants,
                        'presents' => $presents,
                        'absents' => $absents,
                        'taux_presence' => $tauxPresence,
                        'peut_modifier' => $datePresence->isToday() || $datePresence->isFuture()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération enfants classe: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des enfants'
            ], 500);
        }
    }

    /**
     * POST /api/educateur/classes/{classeId}/presences
     * Marquer les présences pour une classe et une date
     */
    public function marquerPresences(Request $request, $classeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $educateur = $user->educateur;

            // Vérifier que l'éducateur a accès à cette classe
            if (!$educateur->classes()->where('classe.id', $classeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette classe'
                ], 403);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'date_presence' => 'required|date',
                'presences' => 'required|array|min:1',
                'presences.*.enfant_id' => 'required|exists:enfant,id',
                'presences.*.statut' => 'required|in:present,absent'
            ], [
                'presences.required' => 'Les données de présence sont obligatoires',
                'presences.*.enfant_id.required' => 'L\'ID de l\'enfant est obligatoire',
                'presences.*.enfant_id.exists' => 'Un enfant spécifié n\'existe pas',
                'presences.*.statut.required' => 'Le statut de présence est obligatoire',
                'presences.*.statut.in' => 'Le statut doit être "present" ou "absent"'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $datePresence = Carbon::parse($request->date_presence);
            $presencesData = $request->presences;

            // Vérifier que tous les enfants appartiennent bien à cette classe
            $enfantIds = collect($presencesData)->pluck('enfant_id');
            $enfantsClasse = Enfant::where('classe_id', $classeId)
                ->whereIn('id', $enfantIds)
                ->pluck('id');

            if ($enfantIds->count() !== $enfantsClasse->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Certains enfants ne sont pas dans cette classe'
                ], 422);
            }

            DB::beginTransaction();

            $presencesTraitees = [];
            $nouvelles = 0;
            $modifiees = 0;

            foreach ($presencesData as $presenceData) {
                $enfantId = $presenceData['enfant_id'];
                $statut = $presenceData['statut'];

                // Vérifier si une présence existe déjà
                $presence = Presence::where([
                    'enfant_id' => $enfantId,
                    'educateur_id' => $educateur->id,
                    'date_presence' => $datePresence
                ])->first();

                if ($presence) {
                    // Mettre à jour si le statut a changé
                    if ($presence->statut !== $statut) {
                        $presence->update(['statut' => $statut]);
                        $modifiees++;
                    }
                    $presencesTraitees[] = $presence;
                } else {
                    // Créer nouvelle présence
                    $nouvellePresence = Presence::create([
                        'enfant_id' => $enfantId,
                        'educateur_id' => $educateur->id,
                        'date_presence' => $datePresence,
                        'statut' => $statut
                    ]);
                    $nouvelles++;
                    $presencesTraitees[] = $nouvellePresence;
                }
            }

            DB::commit();

            // Récalculer les statistiques
            $totalEnfants = count($presencesData);
            $presents = collect($presencesData)->where('statut', 'present')->count();
            $absents = collect($presencesData)->where('statut', 'absent')->count();
            $tauxPresence = $totalEnfants > 0 ? round(($presents / $totalEnfants) * 100, 1) : 0;

            Log::info('Présences marquées avec succès', [
                'educateur_id' => $educateur->id,
                'classe_id' => $classeId,
                'date' => $datePresence->format('Y-m-d'),
                'nouvelles' => $nouvelles,
                'modifiees' => $modifiees
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Présences enregistrées avec succès',
                'data' => [
                    'traitement' => [
                        'nouvelles_presences' => $nouvelles,
                        'presences_modifiees' => $modifiees,
                        'total_traite' => count($presencesTraitees)
                    ],
                    'resume' => [
                        'total_enfants' => $totalEnfants,
                        'presents' => $presents,
                        'absents' => $absents,
                        'taux_presence' => $tauxPresence
                    ],
                    'date_presence' => $datePresence->format('Y-m-d'),
                    'heure_traitement' => now()->format('H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur marquage présences: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement des présences'
            ], 500);
        }
    }

    /**
     * PUT /api/educateur/presences/{presenceId}
     * Modifier une présence individuelle
     */
    public function updatePresence(Request $request, $presenceId): JsonResponse
    {
        try {
            $user = Auth::user();
            $educateur = $user->educateur;

            // Récupérer la présence et vérifier qu'elle appartient à l'éducateur
            $presence = Presence::where('id', $presenceId)
                ->where('educateur_id', $educateur->id)
                ->with(['enfant.classe'])
                ->firstOrFail();

            // Vérifier que l'éducateur a toujours accès à cette classe
            if (!$educateur->classes()->where('classe.id', $presence->enfant->classe_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez plus accès à cette classe'
                ], 403);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'statut' => 'required|in:present,absent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Statut invalide',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ancienStatut = $presence->statut;
            $presence->update(['statut' => $request->statut]);

            Log::info('Présence modifiée', [
                'presence_id' => $presenceId,
                'enfant_id' => $presence->enfant_id,
                'ancien_statut' => $ancienStatut,
                'nouveau_statut' => $request->statut,
                'educateur_id' => $educateur->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Présence modifiée avec succès',
                'data' => [
                    'presence_id' => $presence->id,
                    'enfant' => [
                        'id' => $presence->enfant->id,
                        'nom_complet' => "{$presence->enfant->prenom} {$presence->enfant->nom}"
                    ],
                    'ancien_statut' => $ancienStatut,
                    'nouveau_statut' => $presence->statut,
                    'date_presence' => $presence->date_presence->format('Y-m-d'),
                    'updated_at' => $presence->updated_at->format('H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur modification présence: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de la présence'
            ], 500);
        }
    }

    /**
     * GET /api/educateur/classes/{classeId}/presences/historique
     * Historique des présences d'une classe
     */
    public function getHistoriqueClasse(Request $request, $classeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $educateur = $user->educateur;

            // Vérifier l'accès à la classe
            if (!$educateur->classes()->where('classe.id', $classeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette classe'
                ], 403);
            }

            // Paramètres de filtre
            $dateDebut = $request->get('date_debut', now()->subDays(30)->format('Y-m-d'));
            $dateFin = $request->get('date_fin', now()->format('Y-m-d'));
            $perPage = $request->get('per_page', 10);

            // Récupérer l'historique
            $historique = Presence::where('educateur_id', $educateur->id)
                ->whereHas('enfant', function($query) use ($classeId) {
                    $query->where('classe_id', $classeId);
                })
                ->whereBetween('date_presence', [$dateDebut, $dateFin])
                ->with(['enfant:id,nom,prenom,classe_id'])
                ->orderBy('date_presence', 'desc')
                ->paginate($perPage);

            // Grouper par date
            $historiqueGroupe = $historique->getCollection()->groupBy('date_presence')->map(function($presences, $date) {
                $presents = $presences->where('statut', 'present')->count();
                $absents = $presences->where('statut', 'absent')->count();
                $total = $presences->count();

                return [
                    'date' => $date,
                    'date_libelle' => Carbon::parse($date)->locale('fr')->isoFormat('dddd DD MMMM YYYY'),
                    'resume' => [
                        'total' => $total,
                        'presents' => $presents,
                        'absents' => $absents,
                        'taux_presence' => $total > 0 ? round(($presents / $total) * 100, 1) : 0
                    ],
                    'presences' => $presences->map(function($presence) {
                        return [
                            'enfant_nom_complet' => "{$presence->enfant->prenom} {$presence->enfant->nom}",
                            'statut' => $presence->statut
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $historiqueGroupe->values(),
                'pagination' => [
                    'current_page' => $historique->currentPage(),
                    'last_page' => $historique->lastPage(),
                    'per_page' => $historique->perPage(),
                    'total' => $historique->total()
                ],
                'filters' => [
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                    'classe_id' => $classeId
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur historique classe: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique'
            ], 500);
        }
    }

    /**
     * GET /api/educateur/presences/statistiques
     * Statistiques générales des présences de l'éducateur
     */
    public function getStatistiquesEducateur(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $educateur = $user->educateur;

            $periode = $request->get('periode', '30'); // jours
            $dateDebut = now()->subDays($periode);

            // Statistiques générales
            $totalPresences = Presence::where('educateur_id', $educateur->id)
                ->where('date_presence', '>=', $dateDebut)
                ->count();

            $presentsTotal = Presence::where('educateur_id', $educateur->id)
                ->where('date_presence', '>=', $dateDebut)
                ->where('statut', 'present')
                ->count();

            $absentsTotal = Presence::where('educateur_id', $educateur->id)
                ->where('date_presence', '>=', $dateDebut)
                ->where('statut', 'absent')
                ->count();

            // Taux de présence par classe
            $statsParClasse = $educateur->classes()
                ->select('classe.id', 'classe.nom', 'classe.niveau')
                ->get()
                ->map(function($classe) use ($educateur, $dateDebut) {
                    $presencesClasse = Presence::where('educateur_id', $educateur->id)
                        ->whereHas('enfant', function($q) use ($classe) {
                            $q->where('classe_id', $classe->id);
                        })
                        ->where('date_presence', '>=', $dateDebut);

                    $total = $presencesClasse->count();
                    $presents = (clone $presencesClasse)->where('statut', 'present')->count();

                    return [
                        'classe_id' => $classe->id,
                        'classe_nom' => $classe->nom,
                        'classe_niveau' => $classe->niveau,
                        'total_presences' => $total,
                        'presents' => $presents,
                        'absents' => $total - $presents,
                        'taux_presence' => $total > 0 ? round(($presents / $total) * 100, 1) : 0
                    ];
                });

            // Évolution sur la période (par semaine)
            $evolutionHebdo = [];
            for ($i = 0; $i < ($periode / 7); $i++) {
                $debutSemaine = now()->subWeeks($i + 1)->startOfWeek();
                $finSemaine = $debutSemaine->copy()->endOfWeek();

                $presencesSemaine = Presence::where('educateur_id', $educateur->id)
                    ->whereBetween('date_presence', [$debutSemaine, $finSemaine]);

                $totalSemaine = $presencesSemaine->count();
                $presentsSemaine = (clone $presencesSemaine)->where('statut', 'present')->count();

                $evolutionHebdo[] = [
                    'semaine' => "Semaine du " . $debutSemaine->format('d/m'),
                    'total' => $totalSemaine,
                    'presents' => $presentsSemaine,
                    'taux' => $totalSemaine > 0 ? round(($presentsSemaine / $totalSemaine) * 100, 1) : 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'periode' => "{$periode} derniers jours",
                    'resume_general' => [
                        'total_presences' => $totalPresences,
                        'total_presents' => $presentsTotal,
                        'total_absents' => $absentsTotal,
                        'taux_presence_global' => $totalPresences > 0 ? round(($presentsTotal / $totalPresences) * 100, 1) : 0
                    ],
                    'statistiques_par_classe' => $statsParClasse,
                    'evolution_hebdomadaire' => array_reverse($evolutionHebdo),
                    'nombre_classes_actives' => $educateur->classes()->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur statistiques éducateur: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }
}
