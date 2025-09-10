<?php
// app/Http/Requests/ClasseIndexRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClasseIndexRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user() && in_array(auth()->user()->role, ['admin', 'educateur']);
    }

    public function rules()
    {
        return [
            'niveau' => 'string|max:100',
            'capacite_min' => 'integer|min:1',
            'capacite_max' => 'integer|max:50',
            'search' => 'string|max:255',
            'sort_by' => 'in:nom,niveau,capacite_max,created_at',
            'sort_order' => 'in:asc,desc',
            'per_page' => 'integer|min:5|max:100',
            'page' => 'integer|min:1'
        ];
    }

    public function messages()
    {
        return [
            'niveau.max' => 'Le niveau ne peut pas dépasser 100 caractères.',
            'capacite_min.min' => 'La capacité minimale doit être d\'au moins 1.',
            'capacite_max.max' => 'La capacité maximale ne peut pas dépasser 50.',
            'search.max' => 'La recherche ne peut pas dépasser 255 caractères.',
            'sort_by.in' => 'Le tri doit être par nom, niveau, capacite_max ou created_at.',
            'sort_order.in' => 'L\'ordre de tri doit être asc ou desc.',
            'per_page.min' => 'Le nombre d\'éléments par page doit être d\'au moins 5.',
            'per_page.max' => 'Le nombre d\'éléments par page ne peut pas dépasser 100.',
            'page.min' => 'Le numéro de page doit être d\'au moins 1.'
        ];
    }
}