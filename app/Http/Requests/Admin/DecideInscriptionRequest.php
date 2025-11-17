<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideInscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // déjà couvert par middleware role:admin
    }

    public function rules(): array
    {
        return [
            'action'            => ['required','string', Rule::in([
                // on accepte fr/en
                'accepter','accept','accepted',
                'wait','waiting','attente','mettre_en_attente','liste_attente',
                'reject','refuser','rejected',
            ])],
            'classe_id'         => ['nullable','integer','exists:classe,id'],
            'frais_inscription' => ['nullable','numeric','min:0'],
            'frais_mensuel'     => ['nullable','numeric','min:0'],
            'remarques'         => ['nullable','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'L’action est obligatoire.',
            'action.in'       => 'Action invalide (accept / wait / reject).',
        ];
    }
}
