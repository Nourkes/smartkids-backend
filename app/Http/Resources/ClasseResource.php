<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'niveau' => $this->niveau,
            'capacite_max' => $this->capacite_max,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relations conditionnelles
            'enfants' => $this->whenLoaded('enfants', function () {
                return EnfantResource::collection($this->enfants);
            }),

            'educateurs' => $this->whenLoaded('educateurs', function () {
                return $this->educateurs->map(function ($educateur) {
                    return [
                        'id' => $educateur->id,
                        'user_id' => $educateur->user_id,
                        'nom_complet' => $educateur->user->name ?? null,
                        'email' => $educateur->user->email ?? null,
                        'specialite' => $educateur->specialite,
                        'telephone' => $educateur->telephone,
                    ];
                });
            }),

            'matieres' => $this->whenLoaded('matieres', function () {
                return $this->matieres->map(function ($matiere) {
                    return [
                        'id' => $matiere->id,
                        'nom' => $matiere->nom,
                        'description' => $matiere->description,
                        // Données du pivot si elles existent
                        'heures_par_semaine' => $matiere->pivot->heures_par_semaine ?? null,
                        'objectifs_specifiques' => $matiere->pivot->objectifs_specifiques ?? null,
                    ];
                });
            }),

            // Statistiques calculées
            'stats' => [
                'nombre_enfants' => $this->enfants_count ?? $this->enfants()->count(),
                'places_libres' => $this->capacite_max - ($this->enfants_count ?? $this->enfants()->count()),
                'taux_occupation' => $this->capacite_max > 0 
                    ? round((($this->enfants_count ?? $this->enfants()->count()) / $this->capacite_max) * 100, 1)
                    : 0,
                'nombre_educateurs' => $this->educateurs_count ?? $this->educateurs()->count(),
            ],

            // Métadonnées utiles
            'meta' => [
                'is_full' => ($this->enfants_count ?? $this->enfants()->count()) >= $this->capacite_max,
                'has_educateurs' => ($this->educateurs_count ?? $this->educateurs()->count()) > 0,
                'niveau_display' => $this->getNiveauDisplayName(),
                'age_groupe' => $this->getAgeGroupe(),
            ],
        ];
    }

    /**
     * Obtenir le nom d'affichage du niveau
     */
    private function getNiveauDisplayName(): string
    {
        $niveauMap = [
            'petite_section' => 'Petite Section',
            'moyenne_section' => 'Moyenne Section',
            'grande_section' => 'Grande Section',
            'creche' => 'Crèche',
            'nursery' => 'Nursery',
            'kindergarten' => 'Kindergarten',
        ];

        return $niveauMap[$this->niveau] ?? ucfirst(str_replace('_', ' ', $this->niveau));
    }

    /**
     * Déterminer la tranche d'âge selon le niveau
     */
    private function getAgeGroupe(): string
    {
        $ageMap = [
            'creche' => '0-2 ans',
            'nursery' => '2-3 ans',
            'petite_section' => '3-4 ans',
            'moyenne_section' => '4-5 ans',
            'grande_section' => '5-6 ans',
            'kindergarten' => '5-6 ans',
        ];

        return $ageMap[$this->niveau] ?? 'Non défini';
    }

    /**
     * Ajouter des métadonnées supplémentaires à la réponse
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => '1.0',
            ],
        ];
    }

    /**
     * Personnaliser les liens de la ressource
     */
    public function withResponse(Request $request, $response): void
    {
        // Ajouter des en-têtes personnalisés si nécessaire
        $response->header('X-Resource-Type', 'Classe');
    }
}