<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Educateur;
use Illuminate\Support\Facades\DB;
use Exception;

class ClasseEducateurService
{
    /**
     * Assigner un éducateur à une classe
     */
    public function assignEducateurToClasse(array $data)
    {
        try {
            $educateur = Educateur::findOrFail($data['educateur_id']);
            $classe = Classe::findOrFail($data['classe_id']);

            if ($educateur->classes()->where('classe_id', $data['classe_id'])->exists()) {
                throw new Exception('Cet éducateur est déjà assigné à cette classe.');
            }

            // Assigner l'éducateur à la classe
            $educateur->classes()->attach($data['classe_id'], [
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return [
                'educateur' => $educateur->load('user'),
                'classe' => $classe,
                'assigned_at' => now()
            ];
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Retirer un éducateur d'une classe
     */
    public function removeEducateurFromClasse(int $educateurId, int $classeId)
    {
        try {
            $educateur = Educateur::findOrFail($educateurId);
            
            // Vérifier si l'éducateur est assigné à cette classe
            if (!$educateur->classes()->where('classe_id', $classeId)->exists()) {
                throw new Exception('Cet éducateur n\'est pas assigné à cette classe.');
            }

            // Retirer l'éducateur de la classe
            $educateur->classes()->detach($classeId);

            return true;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Obtenir tous les éducateurs d'une classe
     */
    public function getEducateursByClasse(int $classeId)
    {
        $classe = Classe::findOrFail($classeId);
        
        return $classe->educateurs()
            ->with(['user'])
            ->orderBy('educateur_classe.created_at', 'desc')
            ->get();
    }

    /**
     * Obtenir toutes les classes d'un éducateur
     */
    public function getClassesByEducateur(int $educateurId)
    {
        $educateur = Educateur::findOrFail($educateurId);
        
        return $educateur->classes()
            ->orderBy('educateur_classe.created_at', 'desc')
            ->get();
    }

    /**
     * Obtenir les éducateurs disponibles (non assignés à une classe spécifique)
     */
    public function getAvailableEducateursByClasse(int $classeId)
    {
        return Educateur::with(['user'])
            ->whereDoesntHave('classes', function ($query) use ($classeId) {
                $query->where('classe_id', $classeId);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Changer l'éducateur d'une classe
     */
    public function changeEducateurForClasse(int $ancienEducateurId, int $nouveauEducateurId, int $classeId)
    {
        return DB::transaction(function () use ($ancienEducateurId, $nouveauEducateurId, $classeId) {
            // Retirer l'ancien éducateur
            $this->removeEducateurFromClasse($ancienEducateurId, $classeId);

            // Assigner le nouveau éducateur
            $result = $this->assignEducateurToClasse([
                'educateur_id' => $nouveauEducateurId,
                'classe_id' => $classeId
            ]);

            return $result;
        });
    }

    /**
     * Obtenir un résumé de toutes les affectations
     */
    public function getAffectationsResume()
    {
        return Classe::with(['educateurs.user'])
            ->orderBy('nom')
            ->get()
            ->map(function ($classe) {
                return [
                    'classe' => [
                        'id' => $classe->id,
                        'nom' => $classe->nom,
                        'niveau' => $classe->niveau,
                        'capacite_max' => $classe->capacite_max
                    ],
                    'educateurs' => $classe->educateurs->map(function ($educateur) {
                        return [
                            'id' => $educateur->id,
                            'nom' => $educateur->user->name,
                            'email' => $educateur->user->email,
                            'diplome' => $educateur->diplome,
                            'assigned_at' => $educateur->pivot->created_at
                        ];
                    }),
                    'nombre_educateurs' => $classe->educateurs->count()
                ];
            });
    }

    /**
     * Assigner plusieurs éducateurs à une classe en une fois
     */
    public function assignMultipleEducateursToClasse(int $classeId, array $educateursIds)
    {
        return DB::transaction(function () use ($classeId, $educateursIds) {
            $results = [];

            foreach ($educateursIds as $educateurId) {
                try {
                    $result = $this->assignEducateurToClasse([
                        'educateur_id' => $educateurId,
                        'classe_id' => $classeId
                    ]);
                    $results['success'][] = $result;
                } catch (Exception $e) {
                    $results['errors'][] = [
                        'educateur_id' => $educateurId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $results;
        });
    }

    /**
     * Obtenir les statistiques des affectations
     */
    public function getAffectationsStats()
    {
        return [
            'total_classes' => Classe::count(),
            'classes_avec_educateurs' => Classe::has('educateurs')->count(),
            'classes_sans_educateurs' => Classe::doesntHave('educateurs')->count(),
            'total_educateurs' => Educateur::count(),
            'educateurs_assignes' => Educateur::has('classes')->count(),
            'educateurs_non_assignes' => Educateur::doesntHave('classes')->count(),
            'total_affectations' => DB::table('educateur_classe')->count()
        ];
    }

    /**
     * Obtenir l'historique des affectations d'un éducateur
     */
    public function getEducateurAffectationsHistory(int $educateurId)
    {
        $educateur = Educateur::findOrFail($educateurId);
        
        return $educateur->classes()
            ->withPivot('created_at', 'updated_at')
            ->orderBy('educateur_classe.created_at', 'desc')
            ->get()
            ->map(function ($classe) {
                return [
                    'classe' => [
                        'id' => $classe->id,
                        'nom' => $classe->nom,
                        'niveau' => $classe->niveau
                    ],
                    'assigned_at' => $classe->pivot->created_at,
                    'updated_at' => $classe->pivot->updated_at
                ];
            });
    }
}