<?php
// app/Http/Requests/Admin/ActiviteUpdateRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActiviteUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'nom'             => 'sometimes|required|string|max:255',
            'description'     => 'nullable|string',
            'type'            => 'nullable|string|max:100',
            'date_activite'   => 'sometimes|date',
            'heure_debut'     => 'sometimes|date_format:H:i',
            'heure_fin'       => 'sometimes|date_format:H:i',
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
