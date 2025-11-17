<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class EnfantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
{
    return [
        'id' => $this->id,
        'nom' => $this->nom,
        'prenom' => $this->prenom,
        'sexe' => $this->sexe,
        'date_naissance' => $this->date_naissance,
        'niveau_classe' => optional($this->classe)->niveau, // remplace 'niveau' par le vrai champ
        'parent_ids' => $this->parents->pluck('id'), // juste les ID
        // ou bien :
        // 'parents' => ParentResource::collection($this->parents),
        'allergies' => $this->allergies,
        'remarques_medicales' => $this->remarques_medicales,
        'created_at' => $this->created_at,
    ];
}

    /**
     * Get the status label
     */
    private function getStatutLabel(): string
    {
        return match($this->statut) {
            'actif' => 'Actif',
            'inactif' => 'Inactif',
            'suspendu' => 'Suspendu',
            default => 'Inconnu'
        };
    }
}