<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EducateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'diplome' => $this->diplome,
            'date_embauche' => $this->date_embauche->format('Y-m-d'),
            'telephone'     => $this->telephone,       // ✅

            'salaire' => $this->when(
                auth()->user()->isAdmin(), 
                $this->salaire
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations (chargées conditionnellement)
            'classes' => ClasseResource::collection($this->whenLoaded('classes')),
            'activites' => ActiviteResource::collection($this->whenLoaded('activites')),
            'photo' => $this->photo, 
            
        ];
    }
}