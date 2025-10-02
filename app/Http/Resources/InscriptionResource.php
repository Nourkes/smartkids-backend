<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InscriptionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'statut'            => $this->statut,
            'annee_scolaire'    => $this->annee_scolaire,
            'niveau_souhaite'   => $this->niveau_souhaite,
            'classe_id'         => $this->classe_id,
            'position_attente'  => $this->position_attente,
            'date_inscription'  => optional($this->date_inscription)->toISOString(),
            'date_traitement'   => optional($this->date_traitement)->toISOString(),

            // extrait utile du formulaire
            'parent' => [
                'nom'        => $this->nom_parent,
                'prenom'     => $this->prenom_parent,
                'email'      => $this->email_parent,
                'telephone'  => $this->telephone_parent,
                'adresse'    => $this->adresse_parent,
                'profession' => $this->profession_parent,
            ],
            'enfant' => [
                'nom'         => $this->nom_enfant,
                'prenom'      => $this->prenom_enfant,
                'date_naissance' => $this->date_naissance_enfant,
                'genre'       => $this->genre_enfant,
                'allergies'   => $this->allergies,
                'problemes_sante' => $this->problemes_sante,
                'medicaments' => $this->medicaments,
            ],

            'documents_fournis' => $this->documents_fournis,
            'remarques'         => $this->remarques,
            'remarques_admin'   => $this->remarques_admin,

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
