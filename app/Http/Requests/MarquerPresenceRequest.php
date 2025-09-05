<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarquerPresenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->educateur;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'classe_id' => 'required|exists:classe,id',
            'date_presence' => 'required|date|before_or_equal:today',
            'presences' => 'required|array|min:1',
            'presences.*.enfant_id' => 'required|exists:enfant,id',
            'presences.*.statut' => 'required|in:present,absent,retard,excuse'
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classe_id.required' => 'La classe est obligatoire',
            'classe_id.exists' => 'La classe sélectionnée n\'existe pas',
            'date_presence.required' => 'La date de présence est obligatoire',
            'date_presence.date' => 'La date de présence doit être une date valide',
            'date_presence.before_or_equal' => 'La date de présence ne peut pas être dans le futur',
            'presences.required' => 'Au moins une présence doit être marquée',
            'presences.array' => 'Les présences doivent être un tableau',
            'presences.min' => 'Au moins une présence doit être marquée',
            'presences.*.enfant_id.required' => 'L\'ID de l\'enfant est obligatoire',
            'presences.*.enfant_id.exists' => 'L\'enfant sélectionné n\'existe pas',
            'presences.*.statut.required' => 'Le statut de présence est obligatoire',
            'presences.*.statut.in' => 'Le statut doit être : présent, absent, retard ou excusé'
        ];
    }
}

// CalendrierRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalendrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->parent;
    }

    public function rules(): array
    {
        return [
            'mois' => 'required|integer|min:1|max:12',
            'annee' => 'required|integer|min:2020|max:2030'
        ];
    }

    public function messages(): array
    {
        return [
            'mois.required' => 'Le mois est obligatoire',
            'mois.integer' => 'Le mois doit être un nombre entier',
            'mois.min' => 'Le mois doit être entre 1 et 12',
            'mois.max' => 'Le mois doit être entre 1 et 12',
            'annee.required' => 'L\'année est obligatoire',
            'annee.integer' => 'L\'année doit être un nombre entier',
            'annee.min' => 'L\'année doit être supérieure à 2020',
            'annee.max' => 'L\'année doit être inférieure à 2030'
        ];
    }
}