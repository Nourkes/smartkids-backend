<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Récupère l’ID depuis la route resource ({menu}) ou fallback {id}
        $menuId = $this->route('menu') ?? $this->route('id');

        $rules = [
            // En update on autorise les MAJ partielles
            'description' => ['sometimes', 'filled', 'string', 'min:10', 'max:1000'],
            'date_menu'   => ['sometimes', 'filled', 'date'],
            'type_repas'  => ['sometimes', 'filled', Rule::in(['lunch', 'snack'])],
            'ingredients' => ['sometimes', 'filled', 'string', 'min:5', 'max:2000'],
        ];

        // Contrainte d’unicité (date_menu, type_repas) en ignorant l’enregistrement courant
        if ($this->hasAny(['date_menu', 'type_repas'])) {
            $rules['date_menu'][] = Rule::unique('menus', 'date_menu')
                ->where(function ($q) {
                    $q->where('type_repas', $this->input('type_repas', $this->route('type_repas')));
                })
                ->ignore($menuId);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'description.min'      => 'La description doit contenir au moins :min caractères.',
            'description.max'      => 'La description ne peut pas dépasser :max caractères.',

            'date_menu.date'       => 'La date du menu doit être une date valide.',
            'date_menu.unique'     => 'Un menu existe déjà pour cette date et ce type de repas.',

            'type_repas.in'        => 'Le type de repas doit être "lunch" (déjeuner) ou "snack" (goûter).',

            'ingredients.min'      => 'La liste des ingrédients doit contenir au moins :min caractères.',
            'ingredients.max'      => 'La liste des ingrédients ne peut pas dépasser :max caractères.',
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'description du menu',
            'date_menu'   => 'date du menu',
            'type_repas'  => 'type de repas',
            'ingredients' => 'ingrédients',
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->filled('type_repas') ? strtolower(trim((string) $this->input('type_repas'))) : null;
        if ($type !== null) {
            $map = [
                'déjeuner' => 'lunch', 'dejeuner' => 'lunch', 'lunch' => 'lunch',
                'goûter'   => 'snack', 'gouter'   => 'snack', 'snack' => 'snack',
            ];
            $type = $map[$type] ?? $type;
        }

        $date = $this->input('date_menu');
        if (!empty($date)) {
            try { $date = Carbon::parse($date)->format('Y-m-d'); } catch (\Throwable $e) {}
        }

        $merge = [];
        if ($type !== null) $merge['type_repas'] = $type;
        if (!empty($date))  $merge['date_menu']  = $date;

        if (array_key_exists('description', $this->all()) && is_string($this->description)) {
            $merge['description'] = trim($this->description);
        }
        if (array_key_exists('ingredients', $this->all()) && is_string($this->ingredients)) {
            $merge['ingredients'] = trim($this->ingredients);
        }

        if (!empty($merge)) $this->merge($merge);
    }
}
