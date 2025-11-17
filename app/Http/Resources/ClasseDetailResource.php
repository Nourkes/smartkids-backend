<?php
// app/Http/Resources/ClasseDetailResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseDetailResource extends JsonResource
{
    /**
     * Version détaillée avec toutes les informations et relations
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'niveau' => $this->niveau,
            'capacite_max' => $this->capacite_max,
            'description' => $this->description,
            
            // Statistiques calculées
            'statistiques' => [
                'nombre_enfants' => $this->whenLoaded('enfants', fn() => $this->enfants->count(), 0),
                'places_disponibles' => $this->whenLoaded('enfants', fn() => $this->capacite_max - $this->enfants->count(), $this->capacite_max),
                'est_complete' => $this->whenLoaded('enfants', fn() => $this->enfants->count() >= $this->capacite_max, false),
                'taux_occupation' => $this->whenLoaded('enfants', function () {
                    return $this->capacite_max > 0 ? 
                        round(($this->enfants->count() / $this->capacite_max) * 100, 1) : 0;
                }, 0),
                'nombre_educateurs' => $this->whenLoaded('educateurs', fn() => $this->educateurs->count(), 0),
                'nombre_matieres' => $this->whenLoaded('matieres', fn() => $this->matieres->count(), 0),
                'moyenne_age' => $this->whenLoaded('enfants', function () {
                    if ($this->enfants->isEmpty()) return null;
                    
                    $totalAge = $this->enfants->sum(function($enfant) {
                        return now()->diffInYears($enfant->date_naissance);
                    });
                    return round($totalAge / $this->enfants->count(), 1);
                }, null),
            ],
            
            // Relations détaillées
            'educateurs' => [
                'liste' => EducateurResource::collection($this->whenLoaded('educateurs')),
                'count' => $this->whenLoaded('educateurs', fn() => $this->educateurs->count(), 0),
                'principal' => $this->whenLoaded('educateurs', function () {
                    $principal = $this->educateurs->where('pivot.est_principal', true)->first();
                    return $principal ? new EducateurResource($principal) : null;
                }, null),
            ],
            
            'enfants' => [
                'liste' => EnfantResource::collection($this->whenLoaded('enfants')),
                'count' => $this->whenLoaded('enfants', fn() => $this->enfants->count(), 0),
                'par_age' => $this->when(
                    $this->relationLoaded('enfants') && $this->enfants->isNotEmpty(),
                    function () {
                        return $this->enfants->groupBy(function($enfant) {
                            return now()->diffInYears($enfant->date_naissance);
                        })->map(function($groupe, $age) {
                            return [
                                'age' => $age,
                                'nombre' => $groupe->count(),
                                'enfants' => $groupe->map(function($enfant) {
                                    return [
                                        'id' => $enfant->id,
                                        'nom' => $enfant->nom,
                                        'prenom' => $enfant->prenom,
                                        'date_naissance' => $enfant->date_naissance->format('Y-m-d')
                                    ];
                                })
                            ];
                        })->values();
                    }
                ),
                'par_sexe' => $this->when(
                    $this->relationLoaded('enfants') && $this->enfants->isNotEmpty(),
                    function () {
                        $stats = $this->enfants->groupBy('sexe');
                        return [
                            'garcons' => $stats->get('M', collect())->count(),
                            'filles' => $stats->get('F', collect())->count(),
                        ];
                    }
                )
            ],
            
            'matieres' => [
                'liste' => MatiereResource::collection($this->whenLoaded('matieres')),
                'count' => $this->whenLoaded('matieres', fn() => $this->matieres->count(), 0),
                'par_type' => $this->when(
                    $this->relationLoaded('matieres') && $this->matieres->isNotEmpty(),
                    function () {
                        return $this->matieres->groupBy('type')->map(function($matieres, $type) {
                            return [
                                'type' => $type,
                                'nombre' => $matieres->count(),
                                'matieres' => $matieres->pluck('nom')->toArray()
                            ];
                        })->values();
                    }
                )
            ],
            
            // Planning et activités
            'planning' => $this->when(
                $this->relationLoaded('activites'),
                function () {
                    return [
                        'activites_aujourd_hui' => $this->activites
                            ->where('date', now()->toDateString())
                            ->map(function($activite) {
                                return [
                                    'id' => $activite->id,
                                    'nom' => $activite->nom,
                                    'heure_debut' => $activite->heure_debut,
                                    'heure_fin' => $activite->heure_fin,
                                    'type' => $activite->type
                                ];
                            }),
                        'prochaine_activite' => $this->getNextActivity(),
                        'nombre_activites_semaine' => $this->activites
                            ->whereBetween('date', [
                                now()->startOfWeek()->toDateString(),
                                now()->endOfWeek()->toDateString()
                            ])->count()
                    ];
                }
            ),
            
            // Présences récentes
            'presences' => $this->when(
                $this->relationLoaded('presences'),
                function () {
                    $aujourdhui = now()->toDateString();
                    $presencesAujourdhui = $this->presences->where('date', $aujourdhui);
                    
                    return [
                        'aujourd_hui' => [
                            'total' => $presencesAujourdhui->count(),
                            'presents' => $presencesAujourdhui->where('est_present', true)->count(),
                            'absents' => $presencesAujourdhui->where('est_present', false)->count(),
                            'taux_presence' => $this->enfants->count() > 0 ? 
                                round(($presencesAujourdhui->where('est_present', true)->count() / $this->enfants->count()) * 100, 1) : 0
                        ],
                        'cette_semaine' => $this->getWeeklyAttendanceStats()
                    ];
                }
            ),
            
            // Métadonnées
            'dates' => [
                'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
                'created_at_human' => $this->created_at?->diffForHumans(),
                'updated_at_human' => $this->updated_at?->diffForHumans(),
            ],
            
            // Informations spécifiques selon le rôle
            $this->mergeWhen($request->user()?->role === 'admin', [
                'gestion' => [
                    'peut_supprimer' => $this->whenLoaded('enfants', fn() => $this->enfants->count() === 0, true),
                    'raison_non_suppression' => $this->whenLoaded('enfants', function () {
                        if ($this->enfants->count() > 0) {
                            return "La classe contient {$this->enfants->count()} enfant(s). Veuillez les réassigner avant la suppression.";
                        }
                        return null;
                    }, null),
                    'peut_modifier_capacite' => true,
                    'capacite_min_recommandee' => $this->whenLoaded('enfants', fn() => $this->enfants->count(), 0),
                    'alertes' => $this->getAdminAlerts(),
                    'derniere_modification_par' => $this->when(
                        $this->updated_by,
                        function () {
                            return [
                                'id' => $this->updatedBy->id,
                                'nom' => $this->updatedBy->nom,
                                'prenom' => $this->updatedBy->prenom,
                                'date' => $this->updated_at->diffForHumans()
                            ];
                        }
                    )
                ]
            ]),
            
            // Informations pour les éducateurs
            $this->mergeWhen($request->user()?->role === 'educateur', [
                'mes_responsabilites' => $this->when(
                    $request->user() && $this->relationLoaded('educateurs'),
                    function () use ($request) {
                        $educateur = $this->educateurs->find($request->user()->id);
                        if (!$educateur) return null;
                        
                        return [
                            'est_principal' => $educateur->pivot->est_principal ?? false,
                            'matieres_assignees' => $educateur->pivot->matieres_assignees ?? [],
                            'depuis' => $educateur->pivot->created_at?->diffForHumans(),
                            'permissions' => $this->getEducateurPermissions($educateur)
                        ];
                    }
                )
            ]),
            
            // Informations pour les parents
            $this->mergeWhen($request->user()?->role === 'parent', [
                'mes_enfants' => $this->when(
                    $request->user() && $this->relationLoaded('enfants'),
                    function () use ($request) {
                        return $this->enfants
                            ->where('parent_id', $request->user()->id)
                            ->map(function($enfant) {
                                return [
                                    'id' => $enfant->id,
                                    'nom' => $enfant->nom,
                                    'prenom' => $enfant->prenom,
                                    'derniere_presence' => $enfant->presences()
                                        ->latest('date')
                                        ->first()?->date?->format('Y-m-d'),
                                    'notes_recentes' => $enfant->notes()
                                        ->where('date', '>=', now()->subWeek())
                                        ->count()
                                ];
                            });
                    }
                )
            ])
        ];
    }
    
    /**
     * Obtenir la prochaine activité de la classe
     */
    private function getNextActivity()
    {
        if (!$this->relationLoaded('activites')) {
            return null;
        }
        
        $prochaine = $this->activites
            ->where('date', '>=', now()->toDateString())
            ->sortBy(['date', 'heure_debut'])
            ->first();
            
        if (!$prochaine) return null;
        
        return [
            'id' => $prochaine->id,
            'nom' => $prochaine->nom,
            'date' => $prochaine->date,
            'heure_debut' => $prochaine->heure_debut,
            'type' => $prochaine->type,
            'dans' => $prochaine->date === now()->toDateString() ? 
                "Aujourd'hui à " . $prochaine->heure_debut :
                now()->parse($prochaine->date)->diffForHumans()
        ];
    }
    
    /**
     * Obtenir les statistiques de présence de la semaine
     */
    private function getWeeklyAttendanceStats()
    {
        if (!$this->relationLoaded('presences') || !$this->relationLoaded('enfants')) {
            return null;
        }
        
        $debutSemaine = now()->startOfWeek();
        $finSemaine = now()->endOfWeek();
        
        $presencesSemaine = $this->presences
            ->whereBetween('date', [$debutSemaine->toDateString(), $finSemaine->toDateString()]);
            
        $totalPossible = $this->enfants->count() * 5; // 5 jours ouvrables
        $totalPresent = $presencesSemaine->where('est_present', true)->count();
        
        return [
            'taux_presence_moyen' => $totalPossible > 0 ? 
                round(($totalPresent / $totalPossible) * 100, 1) : 0,
            'total_presences' => $totalPresent,
            'total_possible' => $totalPossible,
            'meilleur_jour' => $this->getBestAttendanceDay($presencesSemaine),
            'tendance' => $this->getAttendanceTrend()
        ];
    }
    
    /**
     * Obtenir le meilleur jour de présence
     */
    private function getBestAttendanceDay($presences)
    {
        $parJour = $presences
            ->where('est_present', true)
            ->groupBy(function($presence) {
                return now()->parse($presence->date)->locale('fr')->dayName;
            });
            
        if ($parJour->isEmpty()) return null;
        
        $meilleurJour = $parJour->sortByDesc(function($presences) {
            return $presences->count();
        })->first();
        
        return [
            'jour' => $parJour->search($meilleurJour),
            'nombre_presences' => $meilleurJour->count()
        ];
    }
    
    /**
     * Obtenir la tendance de présence
     */
    private function getAttendanceTrend()
    {
        // Comparer avec la semaine précédente
        if (!$this->relationLoaded('presences')) return 'stable';
        
        $semaineActuelle = $this->presences
            ->whereBetween('date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->where('est_present', true)
            ->count();
            
        $semainePrecedente = $this->presences
            ->whereBetween('date', [
                now()->subWeek()->startOfWeek()->toDateString(), 
                now()->subWeek()->endOfWeek()->toDateString()
            ])
            ->where('est_present', true)
            ->count();
            
        if ($semaineActuelle > $semainePrecedente) return 'hausse';
        if ($semaineActuelle < $semainePrecedente) return 'baisse';
        return 'stable';
    }
    
    /**
     * Obtenir les alertes administrateur
     */
    private function getAdminAlerts()
    {
        $alertes = [];
        
        if ($this->relationLoaded('enfants')) {
            // Classe surpeuplée
            if ($this->enfants->count() > $this->capacite_max) {
                $alertes[] = [
                    'type' => 'warning',
                    'message' => 'Classe au-dessus de sa capacité maximale',
                    'details' => "{$this->enfants->count()} enfants pour {$this->capacite_max} places"
                ];
            }
            
            // Classe presque pleine
            if ($this->enfants->count() >= ($this->capacite_max * 0.9) && $this->enfants->count() <= $this->capacite_max) {
                $alertes[] = [
                    'type' => 'info',
                    'message' => 'Classe presque complète',
                    'details' => "Plus que " . ($this->capacite_max - $this->enfants->count()) . " place(s) disponible(s)"
                ];
            }
            
            // Classe sous-utilisée
            if ($this->enfants->count() < ($this->capacite_max * 0.5) && $this->capacite_max > 10) {
                $alertes[] = [
                    'type' => 'warning',
                    'message' => 'Classe sous-utilisée',
                    'details' => "Seulement {$this->enfants->count()} enfants pour {$this->capacite_max} places"
                ];
            }
        }
        
        if ($this->relationLoaded('educateurs') && $this->educateurs->isEmpty()) {
            $alertes[] = [
                'type' => 'error',
                'message' => 'Aucun éducateur assigné à cette classe',
                'details' => 'Veuillez assigner au moins un éducateur'
            ];
        }
        
        return $alertes;
    }
    
    /**
     * Obtenir les permissions d'un éducateur pour cette classe
     */
    private function getEducateurPermissions($educateur)
    {
        $permissions = [
            'peut_prendre_presences' => true,
            'peut_ajouter_notes' => true,
            'peut_voir_tous_enfants' => true,
            'peut_modifier_planning' => false,
            'peut_contacter_parents' => true
        ];
        
        // Permissions supplémentaires pour l'éducateur principal
        if ($educateur->pivot->est_principal ?? false) {
            $permissions['peut_modifier_planning'] = true;
            $permissions['peut_assigner_activites'] = true;
            $permissions['peut_generer_rapports'] = true;
        }
        
        return $permissions;
    }
}