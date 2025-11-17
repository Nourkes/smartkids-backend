<?php
// app/Http/Requests/Admin/ActiviteIndexRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ActiviteIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'date_debut'  => ['sometimes','date'],
            'date_fin'    => ['sometimes','date','after_or_equal:date_debut'],
            'type'        => ['sometimes','string','max:100'],
            'statut'      => ['sometimes','in:planifiee,en_cours,terminee,annulee'],
            'search'      => ['sometimes','string'],
            'sort_by'     => ['sometimes','string'],
            'sort_order'  => ['sometimes','in:asc,desc'],
            'per_page'    => ['sometimes','integer','min:1','max:200'],
        ];
    }
}
