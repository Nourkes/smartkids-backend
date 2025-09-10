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
        ];
    }
}