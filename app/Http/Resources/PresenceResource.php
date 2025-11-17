<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date_presence' => $this->date_presence->format('Y-m-d'),
            'date_libelle' => $this->date_presence->locale('fr')->isoFormat('dddd DD MMMM YYYY'),
            'statut' => $this->statut,
            'enfant' => [
                'id' => $this->enfant->id,
                'nom' => $this->enfant->nom,
                'prenom' => $this->enfant->prenom,
                'nom_complet' => "{$this->enfant->prenom} {$this->enfant->nom}"
            ],
            'educateur' => [
                'id' => $this->educateur->id,
                'nom' => $this->educateur->user->name
            ],
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }
}
