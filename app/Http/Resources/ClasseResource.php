<?php
// app/Http/Resources/ClasseResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'niveau' => $this->niveau,
            'capacite_max' => $this->capacite_max,
            'description' => $this->description,
            
            // Informations calculées
            'nombre_enfants' => $this->whenLoaded('enfants', function () {
                return $this->enfants->count();
            }, 0),
            
            'places_disponibles' => $this->whenLoaded('enfants', function () {
                return $this->capacite_max - $this->enfants->count();
            }, $this->capacite_max),
            
            'est_complete' => $this->whenLoaded('enfants', function () {
                return $this->enfants->count() >= $this->capacite_max;
            }, false),
            
            'taux_occupation' => $this->whenLoaded('enfants', function () {
                return $this->capacite_max > 0 ? 
                    round(($this->enfants->count() / $this->capacite_max) * 100, 1) : 0;
            }, 0),
            
            'nombre_educateurs' => $this->whenLoaded('educateurs', function () {
                return $this->educateurs->count();
            }),
            
            'nombre_matieres' => $this->whenLoaded('matieres', function () {
                return $this->matieres->count();
            }),
            
            // Relations
            'educateurs' => EducateurResource::collection($this->whenLoaded('educateurs')),
            'enfants' => EnfantResource::collection($this->whenLoaded('enfants')),
            'matieres'   => $this->when($this->relationLoaded('matieres'), function () {
    return $this->matieres->map(fn ($m) => [
        'id'  => $m->id,
        'nom' => $m->nom ?? $m->name,
    ]);
}),
            
            // Métadonnées
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Informations conditionnelles pour les admins
            $this->mergeWhen($request->user()?->role === 'admin', [
                'statistiques_detaillees' => $this->when(
                    $this->relationLoaded('enfants') && $this->enfants->isNotEmpty(),
                    function () {
                        $ages = $this->enfants->map(function($enfant) {
                            return now()->diffInYears($enfant->date_naissance);
                        });
                        
                        return [
                            'age_moyen' => round($ages->avg(), 1),
                            'age_min' => $ages->min(),
                            'age_max' => $ages->max(),
                            'repartition_ages' => $ages->groupBy(function($age) { 
                                return $age; 
                            })->map(function($groupe) { 
                                return $groupe->count(); 
                            })
                        ];
                    }
                )
            ])
        ];
    }
}