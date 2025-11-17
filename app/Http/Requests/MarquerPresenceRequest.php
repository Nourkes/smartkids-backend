<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarquerPresencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'educateur';
    }

    public function rules(): array
    {
        return [
            'date_presence' => 'required|date',
            'presences' => 'required|array|min:1',
            'presences.*.enfant_id' => [
                'required',
                'exists:enfant,id',
                // Vérifier que l'enfant appartient à la classe
                Rule::exists('enfant', 'id')->where(function ($query) {
                    $query->where('classe_id', $this->route('classeId'));
                })
            ],
            'presences.*.statut' => 'required|in:present,absent'
        ];
    }

    public function messages(): array
    {
        return [
            'date_presence.required' => 'La date de présence est obligatoire',
            'presences.required' => 'Les données de présence sont obligatoires',
            'presences.min' => 'Au moins une présence doit être marquée',
            'presences.*.enfant_id.required' => 'L\'ID de l\'enfant est obligatoire',
            'presences.*.enfant_id.exists' => 'L\'enfant spécifié n\'existe pas ou n\'appartient pas à cette classe',
            'presences.*.statut.required' => 'Le statut de présence est obligatoire',
            'presences.*.statut.in' => 'Le statut doit être "present" ou "absent"'
        ];
    }
}