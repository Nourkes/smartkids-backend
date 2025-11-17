<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'type' => $this->type,
            'duree' => $this->duree,
            'materiel_requis' => $this->materiel_requis,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}