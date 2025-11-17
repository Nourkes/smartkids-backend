<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEducateurRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Autorisation gérée par AdminAccess middleware
    }

    public function rules()
    {
        $educateur = $this->route('educateur');
        $userId = $educateur ? $educateur->user_id : null;
        
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $userId,
            'password' => 'sometimes|nullable|min:8',
            'diplome' => 'sometimes|required|string|max:255',
            'date_embauche' => 'sometimes|required|date',
            'salaire' => 'sometimes|required|numeric|min:0',
            'telephone'     => ['nullable','string','max:20','regex:/^[0-9+\s().-]{6,20}$/'],
            'photo' => [
            'sometimes',
            'nullable',
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:3072',
            // 'dimensions:min_width=128,min_height=128', // optionnel
        ],

        ];
    }
}