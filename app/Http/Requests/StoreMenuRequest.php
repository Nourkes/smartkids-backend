<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'date_menu' => ['required','date'],
            // "both" = on crée lunch + snack le même jour
            'type'      => ['required', Rule::in(['lunch','snack','both'])],

            'lunch'                   => ['nullable','array'],
            'lunch.description'       => Rule::requiredIf(fn()=>in_array($this->type,['lunch','both'])).'|string|max:1000',
            'lunch.ingredients'       => Rule::requiredIf(fn()=>in_array($this->type,['lunch','both'])).'|string|max:2000',

            'snack'                   => ['nullable','array'],
            'snack.description'       => Rule::requiredIf(fn()=>in_array($this->type,['snack','both'])).'|string|max:1000',
            'snack.ingredients'       => Rule::requiredIf(fn()=>in_array($this->type,['snack','both'])).'|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Le type doit être "lunch", "snack" ou "both".',
            'lunch.description.required' => 'La description du déjeuner est requise.',
            'lunch.ingredients.required' => 'Les ingrédients du déjeuner sont requis.',
            'snack.description.required' => 'La description du goûter est requise.',
            'snack.ingredients.required' => 'Les ingrédients du goûter sont requis.',
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
        // Normalisation des champs
        $type = strtolower(trim((string) $this->input('type_repas')));
        $map  = [
            'déjeuner' => 'lunch', 'dejeuner' => 'lunch', 'lunch' => 'lunch',
            'goûter'   => 'snack', 'gouter'   => 'snack', 'snack' => 'snack',
        ];
        $type = $map[$type] ?? $type;

        $date = $this->input('date_menu');
        if (!empty($date)) {
            try { $date = Carbon::parse($date)->format('Y-m-d'); } catch (\Throwable $e) {}
        }

        $this->merge([
            'description' => is_string($this->description) ? trim($this->description) : $this->description,
            'ingredients' => is_string($this->ingredients) ? trim($this->ingredients) : $this->ingredients,
            'type_repas'  => $type,
            'date_menu'   => $date,
        ]);
    }
}
