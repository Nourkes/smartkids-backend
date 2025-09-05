<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ClasseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Seuls les admins peuvent gérer les classes
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $classeId = $this->route('classe') ? $this->route('classe')->id : null;

        $rules = [
            'nom' => [
                'sometimes',
                'string',
                'max:100',
                'unique:classe,nom' . ($classeId ? ',' . $classeId : ''),
            ],
            'niveau' => [
                'required',
                'string',
                'max:50',
            ],
            'capacite_max' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50', // Limite raisonnable pour une classe de crèche
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'educateur_ids' => [
                'sometimes',
                'array',
            ],
            'educateur_ids.*' => [
                'integer',
                'exists:educateurs,id',
            ],
            'matiere_ids' => [
                'sometimes',
                'array',
            ],
            'matiere_ids.*' => [
                'integer',
                'exists:matieres,id',
            ],
        ];

        // Pour la création, certains champs sont requis
        if ($this->isMethod('post')) {
            $rules['nom'][] = 'required';
            $rules['capacite_max'][] = 'required';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la classe est obligatoire.',
            'nom.unique' => 'Ce nom de classe existe déjà.',
            'nom.max' => 'Le nom de la classe ne peut pas dépasser 100 caractères.',
            
            'niveau.required' => 'Le niveau est obligatoire.',
            'niveau.max' => 'Le niveau ne peut pas dépasser 50 caractères.',
            
            'capacite_max.required' => 'La capacité maximale est obligatoire.',
            'capacite_max.integer' => 'La capacité maximale doit être un nombre entier.',
            'capacite_max.min' => 'La capacité maximale doit être d\'au moins 1.',
            'capacite_max.max' => 'La capacité maximale ne peut pas dépasser 50.',
            
            'description.max' => 'La description ne peut pas dépasser 1000 caractères.',
            
            'educateur_ids.array' => 'Les IDs des éducateurs doivent être un tableau.',
            'educateur_ids.*.integer' => 'Chaque ID d\'éducateur doit être un nombre entier.',
            'educateur_ids.*.exists' => 'L\'éducateur sélectionné n\'existe pas.',
            
            'matiere_ids.array' => 'Les IDs des matières doivent être un tableau.',
            'matiere_ids.*.integer' => 'Chaque ID de matière doit être un nombre entier.',
            'matiere_ids.*.exists' => 'La matière sélectionnée n\'existe pas.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nom' => 'nom de la classe',
            'niveau' => 'niveau',
            'capacite_max' => 'capacité maximale',
            'description' => 'description',
            'educateur_ids' => 'éducateurs',
            'matiere_ids' => 'matières',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Validation personnalisée : vérifier la cohérence des données
            if ($this->has('capacite_max') && $this->has('educateur_ids')) {
                $nbEducateurs = is_array($this->educateur_ids) ? count($this->educateur_ids) : 0;
                $capaciteMax = (int) $this->capacite_max;
                
                // Ratio éducateur/enfant recommandé (exemple: 1 éducateur pour max 8 enfants)
                $ratioMax = 8;
                $educateursNecessaires = ceil($capaciteMax / $ratioMax);
                
                if ($nbEducateurs > 0 && $nbEducateurs < $educateursNecessaires) {
                    $validator->errors()->add(
                        'educateur_ids',
                        "Pour une capacité de {$capaciteMax} enfants, il faut au moins {$educateursNecessaires} éducateur(s)."
                    );
                }
            }
        });
    }
}