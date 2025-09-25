<?php

namespace App\Services;

use App\Models\Inscription;
use App\Models\Classe;
use App\Models\User;
use App\Models\ParentModel;
use App\Models\Enfant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class InscriptionService
{
    /**
     * Créer une nouvelle demande d'inscription
     */
    public function creerDemandeInscription(array $data)
    {
        // Vérifier s'il n'y a pas déjà une inscription en cours
        $inscriptionExistante = Inscription::where('email_parent', $data['email_parent'])
            ->where('nom_enfant', $data['nom_enfant'])
            ->where('prenom_enfant', $data['prenom_enfant'])
            ->where('annee_scolaire', $this->getAnneeScolaireActuelle())
            ->whereIn('statut', [Inscription::STATUT_PENDING, Inscription::STATUT_ACCEPTED, Inscription::STATUT_WAITING])
            ->first();

        if ($inscriptionExistante) {
            throw new \Exception('Une inscription est déjà en cours pour cet enfant cette année scolaire.');
        }

        return Inscription::create(array_merge($data, [
            'annee_scolaire' => $this->getAnneeScolaireActuelle(),
            'date_inscription' => now(),
            'statut' => Inscription::STATUT_PENDING,
        ]));
    }

    /**
     * Traiter automatiquement les inscriptions selon la disponibilité des places
     */
    public function traiterInscriptionAutomatique(Inscription $inscription, int $adminId)
    {
        $classe = $inscription->classe;
        
        if ($classe->hasPlacesDisponibles()) {
            // Accepter automatiquement si places disponibles
            return $inscription->accepter($adminId);
        } else {
            // Mettre en liste d'attente si pas de places
            $inscription->mettreEnListeAttente($adminId, 'Automatiquement mis en liste d\'attente - classe complète');
            return ['status' => 'waiting', 'inscription' => $inscription];
        }
    }

    /**
     * Libérer une place et traiter la liste d'attente
     */
    public function libererPlace(int $classeId, int $adminId)
    {
        return DB::transaction(function () use ($classeId, $adminId) {
            $classe = Classe::findOrFail($classeId);
            
            // Vérifier s'il y a des inscriptions en liste d'attente
            $prochaineInscription = $classe->listeAttente()->first();
            
            if ($prochaineInscription && $classe->hasPlacesDisponibles()) {
                // Remettre en pending pour que l'admin puisse décider
                $prochaineInscription->update([
                    'statut' => Inscription::STATUT_PENDING,
                    'position_attente' => null,
                ]);
                
                // Réorganiser les positions
                Inscription::reorganiserPositionsAttente($classeId);
                
                return $prochaineInscription;
            }
            
            return null;
        });
    }

    /**
     * Obtenir les statistiques des inscriptions
     */
    public function getStatistiques(string $anneeScolaire = null)
    {
        $anneeScolaire = $anneeScolaire ?: $this->getAnneeScolaireActuelle();
        
        $stats = [
            'total' => Inscription::pourAnnee($anneeScolaire)->count(),
            'pending' => Inscription::pourAnnee($anneeScolaire)->pending()->count(),
            'accepted' => Inscription::pourAnnee($anneeScolaire)->accepted()->count(),
            'rejected' => Inscription::pourAnnee($anneeScolaire)->rejected()->count(),
            'waiting' => Inscription::pourAnnee($anneeScolaire)->waiting()->count(),
        ];
        
        // Statistiques par classe
        $statsClasses = Classe::with(['inscriptions' => function ($query) use ($anneeScolaire) {
            $query->pourAnnee($anneeScolaire);
        }])->get()->map(function ($classe) {
            return [
                'classe_id' => $classe->id,
                'classe_nom' => $classe->nom,
                'statistiques' => $classe->getStatistiques(),
                'inscriptions_par_statut' => [
                    'pending' => $classe->inscriptions->where('statut', Inscription::STATUT_PENDING)->count(),
                    'accepted' => $classe->inscriptions->where('statut', Inscription::STATUT_ACCEPTED)->count(),
                    'rejected' => $classe->inscriptions->where('statut', Inscription::STATUT_REJECTED)->count(),
                    'waiting' => $classe->inscriptions->where('statut', Inscription::STATUT_WAITING)->count(),
                ]
            ];
        });
        
        return [
            'general' => $stats,
            'par_classe' => $statsClasses,
            'annee_scolaire' => $anneeScolaire,
        ];
    }

    /**
     * Générer un mot de passe temporaire pour un nouveau parent
     */
    public function genererMotDePasseTemporaire()
    {
        return 'Temp' . random_int(1000, 9999) . '!';
    }

    /**
     * Obtenir l'année scolaire actuelle
     */
    public function getAnneeScolaireActuelle()
    {
        $now = Carbon::now();
        $startYear = $now->month >= 9 ? $now->year : $now->year - 1;
        return $startYear . '-' . ($startYear + 1);
    }

    /**
     * Vérifier si une inscription peut être modifiée
     */
    public function peutEtreModifiee(Inscription $inscription)
    {
        return in_array($inscription->statut, [
            Inscription::STATUT_PENDING,
            Inscription::STATUT_WAITING
        ]);
    }

    /**
     * Obtenir les prochaines actions suggérées pour une inscription
     */
    public function getActionsSugerees(Inscription $inscription)
    {
        $actions = [];
        
        switch ($inscription->statut) {
            case Inscription::STATUT_PENDING:
                $classe = $inscription->classe;
                if ($classe->hasPlacesDisponibles()) {
                    $actions[] = [
                        'action' => 'accepter',
                        'libelle' => 'Accepter (places disponibles)',
                        'priorite' => 'high',
                        'icon' => 'check-circle'
                    ];
                } else {
                    $actions[] = [
                        'action' => 'mettre_en_attente',
                        'libelle' => 'Mettre en liste d\'attente',
                        'priorite' => 'medium',
                        'icon' => 'clock'
                    ];
                }
                
                $actions[] = [
                    'action' => 'refuser',
                    'libelle' => 'Refuser l\'inscription',
                    'priorite' => 'low',
                    'icon' => 'x-circle'
                ];
                break;

            case Inscription::STATUT_WAITING:
                $classe = $inscription->classe;
                if ($classe->hasPlacesDisponibles()) {
                    $actions[] = [
                        'action' => 'accepter_depuis_attente',
                        'libelle' => 'Accepter (place libérée)',
                        'priorite' => 'high',
                        'icon' => 'arrow-up-circle'
                    ];
                }
                
                $actions[] = [
                    'action' => 'refuser',
                    'libelle' => 'Refuser définitivement',
                    'priorite' => 'low',
                    'icon' => 'x-circle'
                ];
                break;

            case Inscription::STATUT_ACCEPTED:
                $actions[] = [
                    'action' => 'voir_enfant',
                    'libelle' => 'Voir le profil enfant',
                    'priorite' => 'medium',
                    'icon' => 'user'
                ];
                
                $actions[] = [
                    'action' => 'voir_parent',
                    'libelle' => 'Voir le profil parent',
                    'priorite' => 'medium',
                    'icon' => 'users'
                ];
                break;

            case Inscription::STATUT_REJECTED:
                $actions[] = [
                    'action' => 'reconsiderer',
                    'libelle' => 'Reconsidérer l\'inscription',
                    'priorite' => 'low',
                    'icon' => 'refresh-ccw'
                ];
                break;
        }
        
        return $actions;
    }

    /**
     * Traiter une inscription (accepter, refuser, mettre en attente)
     */
    public function traiterInscription(Inscription $inscription, string $action, int $adminId, array $options = [])
    {
        return DB::transaction(function () use ($inscription, $action, $adminId, $options) {
            switch ($action) {
                case 'accepter':
                    $classe = $inscription->classe;
                    if (!$classe->hasPlacesDisponibles()) {
                        throw new \Exception('Aucune place disponible dans cette classe');
                    }
                    
                    // Si l'inscription était en liste d'attente, réorganiser les positions
                    if ($inscription->isWaiting()) {
                        $this->reorganiserPositionsApresRetrait($inscription);
                    }
                    
                    $fraisInscription = $options['frais_inscription'] ?? null;
                    $fraisMensuel = $options['frais_mensuel'] ?? null;
                    
                    $result = $inscription->accepter($adminId, $fraisInscription, $fraisMensuel);
                    
                    return [
                        'status' => 'success',
                        'message' => 'Inscription acceptée avec succès',
                        'data' => $result
                    ];

                case 'refuser':
                    if ($inscription->isWaiting()) {
                        $this->reorganiserPositionsApresRetrait($inscription);
                    }
                    
                    $remarques = $options['remarques'] ?? 'Inscription refusée';
                    $inscription->refuser($adminId, $remarques);
                    
                    return [
                        'status' => 'success',
                        'message' => 'Inscription refusée',
                        'data' => $inscription
                    ];

                case 'mettre_en_attente':
                    $remarques = $options['remarques'] ?? 'Mis en liste d\'attente par l\'administrateur';
                    $inscription->mettreEnListeAttente($adminId, $remarques);
                    
                    return [
                        'status' => 'success',
                        'message' => 'Inscription mise en liste d\'attente',
                        'data' => $inscription
                    ];

                case 'accepter_depuis_attente':
                    return $this->accepterDepuisListeAttente($inscription, $adminId, $options);

                case 'reconsiderer':
                    $inscription->update([
                        'statut' => Inscription::STATUT_PENDING,
                        'date_traitement' => null,
                        'remarques_admin' => 'Inscription reconsidérée'
                    ]);
                    
                    return [
                        'status' => 'success',
                        'message' => 'Inscription remise en traitement',
                        'data' => $inscription
                    ];

                default:
                    throw new \Exception('Action non reconnue: ' . $action);
            }
        });
    }

    /**
     * Accepter une inscription depuis la liste d'attente
     */
    private function accepterDepuisListeAttente(Inscription $inscription, int $adminId, array $options = [])
    {
        if (!$inscription->isWaiting()) {
            throw new \Exception('Cette inscription n\'est pas en liste d\'attente');
        }
        
        $classe = $inscription->classe;
        if (!$classe->hasPlacesDisponibles()) {
            throw new \Exception('Aucune place disponible dans cette classe');
        }
        
        // Réorganiser les positions avant acceptation
        $this->reorganiserPositionsApresRetrait($inscription);
        
        $fraisInscription = $options['frais_inscription'] ?? null;
        $fraisMensuel = $options['frais_mensuel'] ?? null;
        
        $result = $inscription->accepter($adminId, $fraisInscription, $fraisMensuel);
        
        return [
            'status' => 'success',
            'message' => 'Inscription acceptée depuis la liste d\'attente',
            'data' => $result
        ];
    }

    /**
     * Réorganiser les positions après retrait d'une inscription de la liste d'attente
     */
    private function reorganiserPositionsApresRetrait(Inscription $inscription)
    {
        if ($inscription->position_attente) {
            Inscription::where('classe_id', $inscription->classe_id)
                      ->where('statut', Inscription::STATUT_WAITING)
                      ->where('position_attente', '>', $inscription->position_attente)
                      ->decrement('position_attente');
        }
    }

    /**
     * Obtenir la liste d'attente d'une classe
     */
    public function getListeAttente(int $classeId, string $anneeScolaire = null)
    {
        $anneeScolaire = $anneeScolaire ?: $this->getAnneeScolaireActuelle();
        
        return Inscription::where('classe_id', $classeId)
            ->where('annee_scolaire', $anneeScolaire)
            ->where('statut', Inscription::STATUT_WAITING)
            ->orderBy('position_attente', 'asc')
            ->with(['classe'])
            ->get()
            ->map(function ($inscription) {
                return [
                    'id' => $inscription->id,
                    'position' => $inscription->position_attente,
                    'nom_complet_enfant' => $inscription->nom_complet_enfant,
                    'nom_complet_parent' => $inscription->nom_complet_parent,
                    'email_parent' => $inscription->email_parent,
                    'telephone_parent' => $inscription->telephone_parent,
                    'date_inscription' => $inscription->date_inscription,
                    'anciennete' => $inscription->created_at->diffForHumans(),
                    'remarques' => $inscription->remarques,
                    'actions_possibles' => $this->getActionsSugerees($inscription)
                ];
            });
    }

    /**
     * Obtenir les inscriptions en attente de traitement
     */
    public function getInscriptionsEnAttente(array $filtres = [])
    {
        $query = Inscription::where('statut', Inscription::STATUT_PENDING)
            ->with(['classe']);
        
        if (isset($filtres['classe_id'])) {
            $query->where('classe_id', $filtres['classe_id']);
        }
        
        if (isset($filtres['annee_scolaire'])) {
            $query->where('annee_scolaire', $filtres['annee_scolaire']);
        } else {
            $query->where('annee_scolaire', $this->getAnneeScolaireActuelle());
        }
        
        $orderBy = $filtres['order_by'] ?? 'date_inscription';
        $orderDir = $filtres['order_dir'] ?? 'asc';
        $query->orderBy($orderBy, $orderDir);
        
        return $query->get()->map(function ($inscription) {
            return [
                'id' => $inscription->id,
                'nom_complet_enfant' => $inscription->nom_complet_enfant,
                'nom_complet_parent' => $inscription->nom_complet_parent,
                'email_parent' => $inscription->email_parent,
                'telephone_parent' => $inscription->telephone_parent,
                'classe' => [
                    'id' => $inscription->classe->id,
                    'nom' => $inscription->classe->nom,
                    'niveau' => $inscription->classe->niveau,
                    'places_disponibles' => $inscription->classe->getNombrePlacesDisponibles()
                ],
                'date_inscription' => $inscription->date_inscription,
                'anciennete' => $inscription->created_at->diffForHumans(),
                'remarques' => $inscription->remarques,
                'documents_fournis' => $inscription->documents_fournis,
                'actions_possibles' => $this->getActionsSugerees($inscription),
                'priorite' => $this->calculerPriorite($inscription)
            ];
        });
    }

    /**
     * Calculer la priorité d'une inscription
     */
    private function calculerPriorite(Inscription $inscription)
    {
        $priorite = 'normale';
        
        // Priorité haute si places disponibles dans la classe
        if ($inscription->classe->hasPlacesDisponibles()) {
            $priorite = 'haute';
        }
        
        // Priorité élevée si inscription ancienne (plus de 30 jours)
        if ($inscription->created_at->diffInDays(now()) > 30) {
            $priorite = $priorite === 'haute' ? 'tres_haute' : 'elevee';
        }
        
        // Priorité basse si classe surchargée
        $tauxOccupation = $inscription->classe->getNombrePlacesOccupees() / $inscription->classe->capacite_max * 100;
        if ($tauxOccupation > 100) {
            $priorite = 'basse';
        }
        
        return $priorite;
    }

    /**
     * Dupliquer les inscriptions acceptées d'une année vers une nouvelle année
     */
    public function dupliquerInscriptions(string $anneeSource, string $anneeCible, array $options = [])
    {
        return DB::transaction(function () use ($anneeSource, $anneeCible, $options) {
            $inscriptionsSource = Inscription::where('annee_scolaire', $anneeSource)
                ->where('statut', Inscription::STATUT_ACCEPTED)
                ->with(['enfant', 'parent', 'classe'])
                ->get();

            $resultats = [
                'total_source' => $inscriptionsSource->count(),
                'dupliquees' => 0,
                'erreurs' => [],
                'inscriptions' => []
            ];

            foreach ($inscriptionsSource as $inscriptionSource) {
                try {
                    // Vérifier si l'enfant n'a pas déjà une inscription pour l'année cible
                    $existeDeja = Inscription::where('enfant_id', $inscriptionSource->enfant_id)
                        ->where('annee_scolaire', $anneeCible)
                        ->exists();

                    if ($existeDeja) {
                        $resultats['erreurs'][] = "L'enfant {$inscriptionSource->nom_complet_enfant} est déjà inscrit pour l'année {$anneeCible}";
                        continue;
                    }

                    // Créer la nouvelle inscription
                    $nouvelleInscription = new Inscription([
                        'annee_scolaire' => $anneeCible,
                        'date_inscription' => now(),
                        'statut' => $options['statut_initial'] ?? Inscription::STATUT_PENDING,
                        
                        // Copier les données parent et enfant
                        'nom_parent' => $inscriptionSource->nom_parent,
                        'prenom_parent' => $inscriptionSource->prenom_parent,
                        'email_parent' => $inscriptionSource->email_parent,
                        'telephone_parent' => $inscriptionSource->telephone_parent,
                        'adresse_parent' => $inscriptionSource->adresse_parent,
                        'profession_parent' => $inscriptionSource->profession_parent,
                        
                        'nom_enfant' => $inscriptionSource->nom_enfant,
                        'prenom_enfant' => $inscriptionSource->prenom_enfant,
                        'date_naissance_enfant' => $inscriptionSource->date_naissance_enfant,
                        'genre_enfant' => $inscriptionSource->genre_enfant,
                        'problemes_sante' => $inscriptionSource->problemes_sante,
                        'allergies' => $inscriptionSource->allergies,
                        'medicaments' => $inscriptionSource->medicaments,
                        'contact_urgence_nom' => $inscriptionSource->contact_urgence_nom,
                        'contact_urgence_telephone' => $inscriptionSource->contact_urgence_telephone,
                        
                        // Relations
                        'classe_id' => $inscriptionSource->classe_id,
                        'enfant_id' => $inscriptionSource->enfant_id,
                        'parent_id' => $inscriptionSource->parent_id,
                        
                        // Autres données
                        'frais_inscription' => $inscriptionSource->frais_inscription,
                        'frais_mensuel' => $inscriptionSource->frais_mensuel,
                        'remarques' => $options['remarques_duplication'] ?? "Inscription dupliquée depuis l'année {$anneeSource}",
                    ]);

                    $nouvelleInscription->save();
                    
                    $resultats['dupliquees']++;
                    $resultats['inscriptions'][] = $nouvelleInscription;

                } catch (\Exception $e) {
                    $resultats['erreurs'][] = "Erreur pour l'enfant {$inscriptionSource->nom_complet_enfant}: " . $e->getMessage();
                }
            }

            return $resultats;
        });
    }

    /**
     * Générer un rapport détaillé des inscriptions
     */
    public function genererRapport(array $options = [])
    {
        $anneeScolaire = $options['annee_scolaire'] ?? $this->getAnneeScolaireActuelle();
        $dateDebut = isset($options['date_debut']) ? Carbon::parse($options['date_debut']) : null;
        $dateFin = isset($options['date_fin']) ? Carbon::parse($options['date_fin']) : null;
        
        $query = Inscription::where('annee_scolaire', $anneeScolaire)
            ->with(['classe']);
        
        if ($dateDebut) {
            $query->whereDate('date_inscription', '>=', $dateDebut);
        }
        
        if ($dateFin) {
            $query->whereDate('date_inscription', '<=', $dateFin);
        }
        
        if (isset($options['statut'])) {
            $query->where('statut', $options['statut']);
        }
        
        if (isset($options['classe_id'])) {
            $query->where('classe_id', $options['classe_id']);
        }
        
        $inscriptions = $query->orderBy('date_inscription', 'desc')->get();
        
        $rapport = [
            'periode' => [
                'annee_scolaire' => $anneeScolaire,
                'date_debut' => $dateDebut?->format('Y-m-d'),
                'date_fin' => $dateFin?->format('Y-m-d'),
            ],
            'filtres' => $options,
            'statistiques' => [
                'total' => $inscriptions->count(),
                'par_statut' => [
                    'pending' => $inscriptions->where('statut', Inscription::STATUT_PENDING)->count(),
                    'accepted' => $inscriptions->where('statut', Inscription::STATUT_ACCEPTED)->count(),
                    'rejected' => $inscriptions->where('statut', Inscription::STATUT_REJECTED)->count(),
                    'waiting' => $inscriptions->where('statut', Inscription::STATUT_WAITING)->count(),
                ],
                'par_classe' => $inscriptions->groupBy('classe_id')->map(function ($groupe) {
                    return [
                        'classe' => $groupe->first()->classe->nom,
                        'total' => $groupe->count(),
                        'accepted' => $groupe->where('statut', Inscription::STATUT_ACCEPTED)->count(),
                    ];
                }),
                'evolution_mensuelle' => $this->getEvolutionMensuelle($inscriptions),
            ],
            'inscriptions' => $inscriptions,
            'generated_at' => now(),
        ];
        
        return $rapport;
    }

    /**
     * Calculer l'évolution mensuelle des inscriptions
     */
    private function getEvolutionMensuelle($inscriptions)
    {
        return $inscriptions->groupBy(function ($inscription) {
            return $inscription->date_inscription->format('Y-m');
        })->map(function ($groupe, $mois) {
            return [
                'mois' => $mois,
                'mois_libelle' => Carbon::createFromFormat('Y-m', $mois)->locale('fr')->isoFormat('MMMM YYYY'),
                'total' => $groupe->count(),
                'accepted' => $groupe->where('statut', Inscription::STATUT_ACCEPTED)->count(),
                'rejected' => $groupe->where('statut', Inscription::STATUT_REJECTED)->count(),
            ];
        })->values();
    }

    /**
     * Notification des parents en cas de changement de statut
     */
    public function notifierParent(Inscription $inscription, string $nouveauStatut, string $message = null)
    {
        // Cette méthode pourrait envoyer un email ou une notification
        // Pour l'instant, on log juste l'événement
        
        $messageNotification = $message ?? $this->genererMessageNotification($inscription, $nouveauStatut);
        
        // Log de la notification
        \Log::info('Notification parent', [
            'inscription_id' => $inscription->id,
            'email_parent' => $inscription->email_parent,
            'nouveau_statut' => $nouveauStatut,
            'message' => $messageNotification
        ]);
        
        // Ici vous pourriez intégrer un service d'email
        // Mail::to($inscription->email_parent)->send(new InscriptionStatusChanged($inscription, $nouveauStatut, $messageNotification));
        
        return true;
    }

    /**
     * Générer un message de notification selon le statut
     */
    private function genererMessageNotification(Inscription $inscription, string $statut)
    {
        switch ($statut) {
            case Inscription::STATUT_ACCEPTED:
                return "Félicitations ! L'inscription de {$inscription->nom_complet_enfant} a été acceptée pour la classe {$inscription->classe->nom}.";
                
            case Inscription::STATUT_REJECTED:
                return "Nous sommes désolés, mais l'inscription de {$inscription->nom_complet_enfant} n'a pas pu être acceptée cette année.";
                
            case Inscription::STATUT_WAITING:
                return "L'inscription de {$inscription->nom_complet_enfant} a été placée en liste d'attente (position #{$inscription->position_attente}). Nous vous contacterons dès qu'une place se libère.";
                
            default:
                return "Le statut de l'inscription de {$inscription->nom_complet_enfant} a été mis à jour.";
        }
    }

    /**
     * Archiver les inscriptions d'une année scolaire terminée
     */
    public function archiverAnnee(string $anneeScolaire)
    {
        return DB::transaction(function () use ($anneeScolaire) {
            $inscriptions = Inscription::where('annee_scolaire', $anneeScolaire)->get();
            
            $resultats = [
                'total' => $inscriptions->count(),
                'archives' => 0,
                'erreurs' => []
            ];
            
            foreach ($inscriptions as $inscription) {
                try {
                    // Marquer comme archivée (ou déplacer vers une table d'archive)
                    $inscription->update([
                        'archived_at' => now(),
                        'remarques_admin' => ($inscription->remarques_admin ?? '') . " [Archivée le " . now()->format('d/m/Y') . "]"
                    ]);
                    
                    $resultats['archives']++;
                    
                } catch (\Exception $e) {
                    $resultats['erreurs'][] = "Erreur pour l'inscription {$inscription->id}: " . $e->getMessage();
                }
            }
            
            return $resultats;
        });
    }
}