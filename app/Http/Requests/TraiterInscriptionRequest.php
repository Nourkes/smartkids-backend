<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TraiterInscriptionRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules()
    {
        return [
            'action' => 'required|in:accepter,refuser,liste_attente',
            'remarques_admin' => 'nullable|string|max:1000',
            'frais_inscription' => 'nullable|numeric|min:0|max:999999.99',
            'frais_mensuel' => 'nullable|numeric|min:0|max:999999.99',
        ];
    }

    public function messages()
    {
        return [
            'action.required' => 'L\'action à effectuer est obligatoire',
            'action.in' => 'L\'action doit être : accepter, refuser ou liste_attente',
            'frais_inscription.numeric' => 'Les frais d\'inscription doivent être un nombre',
            'frais_inscription.min' => 'Les frais d\'inscription ne peuvent pas être négatifs',
            'frais_mensuel.numeric' => 'Les frais mensuels doivent être un nombre',
            'frais_mensuel.min' => 'Les frais mensuels ne peuvent pas être négatifs',
        ];
    }
}