<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ParentSummaryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nom_complet' => $this->user?->name,
            'telephone' => $this->telephone,

            // 👇 À PLAT (comme Flutter attend)
            'contact_urgence_nom' => $this->contact_urgence_nom,
            'contact_urgence_telephone' => $this->contact_urgence_telephone,
        ];
    }
}
