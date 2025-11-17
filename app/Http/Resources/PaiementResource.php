<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaiementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'inscription_id'=> $this->inscription_id,
            'montant'       => (float) $this->montant,
            'type'          => $this->type,
            'methode'       => $this->methode_paiement,
            'statut'        => $this->statut, // en_attente, paye, expire, annule
            'date_echeance' => optional($this->date_echeance)->toDateString(),
            'date_paiement' => optional($this->date_paiement)->toDateString(),
            'reference'     => $this->reference_transaction,
            'remarques'     => $this->remarques,
            'created_at'    => optional($this->created_at)->toISOString(),
            'updated_at'    => optional($this->updated_at)->toISOString(),
        ];
    }
}
