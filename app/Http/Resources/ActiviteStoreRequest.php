<?php
// app/Http/Requests/Admin/ActiviteStoreRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActiviteStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'nom'             => 'required|string|max:255',
            'description'     => 'nullable|string',
            'type'            => 'nullable|string|max:100',
            'date_activite'   => 'required|date|after_or_equal:today',
            'heure_debut'     => 'required|date_format:H:i',
            'heure_fin'       => 'required|date_format:H:i|after:heure_debut',
            'prix'            => 'nullable|numeric|min:0',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:4096',
            'statut'          => 'nullable|in:planifiee,en_cours,terminee,annulee',
            'capacite_max'    => 'nullable|integer|min:1',
            'materiel_requis' => 'nullable|string',
            'consignes'       => 'nullable|string',
            'educateur_ids'   => 'nullable|array',
            'educateur_ids.*' => 'exists:educateurs,id',
        ];
    }
}
