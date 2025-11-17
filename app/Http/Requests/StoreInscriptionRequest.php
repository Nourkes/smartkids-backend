<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ou ta logique d’accès
    }

    public function rules(): array
    {
        return [
            'annee_scolaire'            => 'required|string|max:255',
            'niveau_souhaite'           => 'required|string|max:50',

            // Parent
            'nom_parent'                => 'required|string|max:255',
            'prenom_parent'             => 'required|string|max:255',
            'email_parent'              => 'required|email|max:255',
            'telephone_parent'          => 'required|string|max:255',
            'adresse_parent'            => 'nullable|string',
            'profession_parent'         => 'nullable|string|max:255',

            // Enfant
            'nom_enfant'                => 'required|string|max:255',
            'prenom_enfant'             => 'required|string|max:255',
            'date_naissance_enfant'     => 'required|date',
'genre_enfant' => 'required|in:M,F,garçon,fille,garcon',
            // Médical / docs
            'problemes_sante'           => 'nullable|array',
            'allergies'                 => 'nullable|array',
            'medicaments'               => 'nullable|array',
            'documents_fournis'         => 'nullable|array',

            // Contact d’urgence
            'contact_urgence_nom'       => 'nullable|string|max:255',
            'contact_urgence_telephone' => 'nullable|string|max:255',

            'remarques'                 => 'nullable|string',
        ];
    }
}
