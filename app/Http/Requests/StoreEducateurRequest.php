<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEducateurRequest extends FormRequest
{
    public function authorize()
    {
        // L'autorisation sera gérée par le middleware AdminAccess
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            
            'diplome' => 'required|string|max:255',
            'date_embauche' => 'required|date',
            'salaire' => 'required|numeric|min:0',
            'telephone'     => ['nullable','string','max:20', 'regex:/^[0-9+\s().-]{6,20}$/'],
            'photo' => [
            'nullable',
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:3072',
            // 'dimensions:min_width=128,min_height=128', // optionnel
        ],

        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Le nom est obligatoire',
            'email.required' => 'L\'email est obligatoire',
            'email.unique' => 'Cet email est déjà utilisé',
            
            
            'diplome.required' => 'Le diplôme est obligatoire',
            'date_embauche.required' => 'La date d\'embauche est obligatoire',
            'salaire.required' => 'Le salaire est obligatoire',
            'salaire.numeric' => 'Le salaire doit être numérique',
        ];
    }
}