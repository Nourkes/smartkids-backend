<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Enfant;
use Illuminate\Support\Facades\DB;

class ClasseService
{
    /**
     * Obtenir les statistiques complètes des classes
     */
    public function getClassesStatistics()
    {
        $totalClasses = Classe::count();
        $totalEnfants = Enfant::whereNotNull('classe_id')->count();
        $capaciteTotale = Classe::sum('capacite_max');
        
        // Statistiques de base
        $stats = [
            'total_classes' => $totalClasses,
            'total_enfants' => $totalEnfants,
            'capacite_totale' => $capaciteTotale,
            'places_disponibles' => $capaciteTotale - $totalEnfants,
            'taux_occupation_global' => $capaciteTotale > 0 ? round(($totalEnfants / $capaciteTotale) * 100, 1) : 0,
            'moyenne_enfants_par_classe' => $totalClasses > 0 ? round($totalEnfants / $totalClasses, 1) : 0
        ];

        // Répartition par niveau
        $stats['repartition_par_niveau'] = $this->getStatsParNiveau();

        // Classes par statut
        $stats['classes_par_statut'] = $this->getClassesParStatut();

        // Évolution mensuelle (si vous avez des données historiques)
        $stats['evolution_mensuelle'] = $this->getEvolutionMensuelle();

        return $stats;
    }

    /**
     * Statistiques par niveau
     */
    private function getStatsParNiveau()
    {
        return Classe::select('niveau')
            ->selectRaw('COUNT(*) as nombre_classes')
            ->selectRaw('SUM(capacite_max) as capacite_totale')
            ->selectRaw('(SELECT COUNT(*) FROM enfant WHERE enfant.classe_id IN (SELECT id FROM classe WHERE classe.niveau = classe.niveau)) as nombre_enfants')
            ->groupBy('niveau')
            ->orderBy('niveau')
            ->get()
            ->map(function($niveau) {
                $tauxOccupation = $niveau->capacite_totale > 0 ? 
                    round(($niveau->nombre_enfants / $niveau->capacite_totale) * 100, 1) : 0;
                
                return [
                    'niveau' => $niveau->niveau,
                    'nombre_classes' => $niveau->nombre_classes,
                    'capacite_totale' => $niveau->capacite_totale,
                    'nombre_enfants' => $niveau->nombre_enfants,
                    'places_disponibles' => $niveau->capacite_totale - $niveau->nombre_enfants,
                    'taux_occupation' => $tauxOccupation
                ];
            })
            ->toArray();
    }

    /**
     * Classes par statut (vides, partielles, complètes, surpeuplées)
     */
    private function getClassesParStatut()
    {
        $classes = Classe::withCount('enfants')->get();
        
        $stats = [
            'vides' => 0,
            'partielles' => 0,
            'completes' => 0,
            'surpeuplees' => 0
        ];

        foreach ($classes as $classe) {
            if ($classe->enfants_count === 0) {
                $stats['vides']++;
            } elseif ($classe->enfants_count < $classe->capacite_max) {
                $stats['partielles']++;
            } elseif ($classe->enfants_count === $classe->capacite_max) {
                $stats['completes']++;
            } else {
                $stats['surpeuplees']++;
            }
        }

        return $stats;
    }

    /**
     * Évolution mensuelle des inscriptions
     */
    private function getEvolutionMensuelle($mois = 6)
    {
        $evolution = [];
        
        for ($i = $mois - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $moisAnnee = $date->format('Y-m');
            
            // Nombre d'enfants inscrits ce mois-là
            $enfantsInscrits = Enfant::whereNotNull('classe_id')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            
            // Nombre total d'enfants à la fin de ce mois
            $totalEnfants = Enfant::whereNotNull('classe_id')
                ->where('created_at', '<=', $date->endOfMonth())
                ->count();
            
            $evolution[] = [
                'mois' => $moisAnnee,
                'mois_libelle' => $date->locale('fr')->isoFormat('MMMM YYYY'),
                'enfants_inscrits' => $enfantsInscrits,
                'total_enfants' => $totalEnfants
            ];
        }

        return $evolution;
    }

    /**
     * Vérifier la disponibilité d'un nom de classe
     */
    public function isNomDisponible($nom, $excludeId = null)
    {
        $query = Classe::where('nom', $nom);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return !$query->exists();
    }

    /**
     * Obtenir les niveaux disponibles avec statistiques
     */
    public function getNiveauxAvecStatistiques()
    {
        return Classe::select('niveau')
            ->selectRaw('COUNT(*) as nombre_classes')
            ->selectRaw('SUM(capacite_max) as capacite_totale')
            ->selectRaw('AVG(capacite_max) as capacite_moyenne')
            ->groupBy('niveau')
            ->orderBy('niveau')
            ->get()
            ->map(function($niveau) {
                $nombreEnfants = DB::table('enfant')
                    ->join('classe', 'enfant.classe_id', '=', 'classe.id')
                    ->where('classe.niveau', $niveau->niveau)
                    ->count();
                
                return [
                    'niveau' => $niveau->niveau,
                    'nombre_classes' => $niveau->nombre_classes,
                    'capacite_totale' => $niveau->capacite_totale,
                    'capacite_moyenne' => round($niveau->capacite_moyenne, 1),
                    'nombre_enfants' => $nombreEnfants,
                    'places_disponibles' => $niveau->capacite_totale - $nombreEnfants,
                    'taux_occupation' => $niveau->capacite_totale > 0 ? 
                        round(($nombreEnfants / $niveau->capacite_totale) * 100, 1) : 0
                ];
            });
    }

    /**
     * Obtenir les classes avec le plus de places disponibles
     */
    public function getClassesAvecPlusDePlaces($limit = 5)
    {
        return Classe::with(['enfants:id,classe_id'])
            ->get()
            ->map(function($classe) {
                $nombreEnfants = $classe->enfants->count();
                $placesDisponibles = $classe->capacite_max - $nombreEnfants;
                
                return [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'niveau' => $classe->niveau,
                    'capacite_max' => $classe->capacite_max,
                    'nombre_enfants' => $nombreEnfants,
                    'places_disponibles' => $placesDisponibles,
                    'taux_occupation' => $classe->capacite_max > 0 ? 
                        round(($nombreEnfants / $classe->capacite_max) * 100, 1) : 0
                ];
            })
            ->filter(function($classe) {
                return $classe['places_disponibles'] > 0;
            })
            ->sortByDesc('places_disponibles')
            ->take($limit)
            ->values();
    }

    /**
     * Obtenir les classes critiques (surpeuplées ou proches de la capacité)
     */
    public function getClassesCritiques()
    {
        return Classe::with(['enfants:id,classe_id'])
            ->get()
            ->map(function($classe) {
                $nombreEnfants = $classe->enfants->count();
                $tauxOccupation = $classe->capacite_max > 0 ? 
                    ($nombreEnfants / $classe->capacite_max) * 100 : 0;
                
                return [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'niveau' => $classe->niveau,
                    'capacite_max' => $classe->capacite_max,
                    'nombre_enfants' => $nombreEnfants,
                    'taux_occupation' => round($tauxOccupation, 1),
                    'statut' => $this->getStatutClasse($tauxOccupation)
                ];
            })
            ->filter(function($classe) {
                return $classe['taux_occupation'] >= 90; // Classes à 90% ou plus
            })
            ->sortByDesc('taux_occupation')
            ->values();
    }

    /**
     * Déterminer le statut d'une classe selon son taux d'occupation
     */
    private function getStatutClasse($tauxOccupation)
    {
        if ($tauxOccupation >= 100) {
            return 'complete';
        } elseif ($tauxOccupation >= 90) {
            return 'critique';
        } elseif ($tauxOccupation >= 70) {
            return 'normale';
        } elseif ($tauxOccupation > 0) {
            return 'faible';
        } else {
            return 'vide';
        }
    }

    /**
     * Recherche intelligente de classes
     */
    public function rechercheIntelligente($terme, $options = [])
    {
        $query = Classe::with(['educateurs.user:id,nom,prenom', 'enfants:id,classe_id']);
        
        // Recherche textuelle
        $query->where(function($q) use ($terme) {
            $q->where('nom', 'LIKE', "%{$terme}%")
              ->orWhere('niveau', 'LIKE', "%{$terme}%")
              ->orWhere('description', 'LIKE', "%{$terme}%");
        });

        // Options de filtrage
        if (isset($options['niveau'])) {
            $query->where('niveau', $options['niveau']);
        }

        if (isset($options['capacite_min'])) {
            $query->where('capacite_max', '>=', $options['capacite_min']);
        }

        if (isset($options['capacite_max'])) {
            $query->where('capacite_max', '<=', $options['capacite_max']);
        }

        if (isset($options['avec_educateurs']) && $options['avec_educateurs']) {
            $query->has('educateurs');
        }

        $classes = $query->get();

        // Filtres post-requête
        if (isset($options['taux_occupation_min']) || isset($options['taux_occupation_max'])) {
            $classes = $classes->filter(function($classe) use ($options) {
                $tauxOccupation = $classe->capacite_max > 0 ? 
                    ($classe->enfants->count() / $classe->capacite_max) * 100 : 0;
                
                if (isset($options['taux_occupation_min']) && $tauxOccupation < $options['taux_occupation_min']) {
                    return false;
                }
                
                if (isset($options['taux_occupation_max']) && $tauxOccupation > $options['taux_occupation_max']) {
                    return false;
                }
                
                return true;
            });
        }

        // Ajouter des informations calculées
        return $classes->map(function($classe) {
            $nombreEnfants = $classe->enfants->count();
            $classe->nombre_enfants = $nombreEnfants;
            $classe->places_disponibles = $classe->capacite_max - $nombreEnfants;
            $classe->taux_occupation = $classe->capacite_max > 0 ? 
                round(($nombreEnfants / $classe->capacite_max) * 100, 1) : 0;
            $classe->nombre_educateurs = $classe->educateurs->count();
            $classe->statut = $this->getStatutClasse($classe->taux_occupation);
            
            unset($classe->enfants); // Nettoyer pour l'API
            return $classe;
        })->values();
    }

    /**
     * Rapport mensuel des classes
     */
    public function genererRapportMensuel($mois = null, $annee = null)
    {
        $mois = $mois ?? now()->month;
        $annee = $annee ?? now()->year;
        
        $debut = now()->create($annee, $mois, 1)->startOfMonth();
        $fin = $debut->copy()->endOfMonth();
        
        // Nouvelles inscriptions ce mois
        $nouvellesInscriptions = Enfant::whereNotNull('classe_id')
            ->whereBetween('created_at', [$debut, $fin])
            ->count();
        
        // Désinscriptions ce mois (si vous avez un soft delete ou un champ de fin)
        $desinscriptions = Enfant::onlyTrashed()
            ->whereBetween('deleted_at', [$debut, $fin])
            ->count();
        
        // Statistiques générales
        $stats = $this->getClassesStatistics();
        
        return [
            'periode' => [
                'mois' => $mois,
                'annee' => $annee,
                'libelle' => $debut->locale('fr')->isoFormat('MMMM YYYY'),
                'debut' => $debut->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d')
            ],
            'nouvelles_inscriptions' => $nouvellesInscriptions,
            'desinscriptions' => $desinscriptions,
            'solde_inscriptions' => $nouvellesInscriptions - $desinscriptions,
            'statistiques_generales' => $stats,
            'classes_critiques' => $this->getClassesCritiques(),
            'classes_disponibles' => $this->getClassesAvecPlusDePlaces(10),
            'generated_at' => now()->format('Y-m-d H:i:s')
        ];
    }
}