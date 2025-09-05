<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UpdateEducateurRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Seuls les admins peuvent modifier des éducateurs
        return auth()->user() && auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $educateur = $this->route('educateur');
        $userId = $educateur ? $educateur->user_id : null;

        return [
            // Données utilisateur (optionnelles pour update)
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/'
            ],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'password' => [
                'sometimes',
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ],

            // Données spécifiques à l'éducateur (optionnelles)
            'diplome' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'in:bac,licence,master,doctorat,cap_petite_enfance,bts_education,deug,autre'
            ],
            'date_embauche' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:today',
                'after:1990-01-01'
            ],
            'salaire' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'decimal:0,2'
            ],

            // Classes assignées (optionnel)
            'classes' => [
                'sometimes',
                'array',
                'max:10'
            ],
            'classes.*' => [
                'exists:classe,id',
                'distinct'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Messages pour name
            'name.required' => 'Le nom est obligatoire.',
            'name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'name.regex' => 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.',

            // Messages pour email
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'email.max' => 'L\'adresse email ne peut pas dépasser 255 caractères.',

            // Messages pour password
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',

            // Messages pour diplome
            'diplome.required' => 'Le diplôme est obligatoire.',
            'diplome.in' => 'Le diplôme sélectionné n\'est pas valide.',
            'diplome.max' => 'Le diplôme ne peut pas dépasser 255 caractères.',

            // Messages pour date_embauche
            'date_embauche.required' => 'La date d\'embauche est obligatoire.',
            'date_embauche.date' => 'La date d\'embauche doit être une date valide.',
            'date_embauche.before_or_equal' => 'La date d\'embauche ne peut pas être dans le futur.',
            'date_embauche.after' => 'La date d\'embauche doit être après 1990.',

            // Messages pour salaire
            'salaire.required' => 'Le salaire est obligatoire.',
            'salaire.numeric' => 'Le salaire doit être un nombre.',
            'salaire.min' => 'Le salaire doit être positif.',
            'salaire.max' => 'Le salaire ne peut pas dépasser 999,999.99.',
            'salaire.decimal' => 'Le salaire peut avoir au maximum 2 décimales.',

            // Messages pour classes
            'classes.array' => 'Les classes doivent être un tableau.',
            'classes.max' => 'Un éducateur ne peut pas être assigné à plus de 10 classes.',
            'classes.*.exists' => 'Une ou plusieurs classes sélectionnées n\'existent pas.',
            'classes.*.distinct' => 'Les classes ne peuvent pas être dupliquées.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse email',
            'password' => 'mot de passe',
            'password_confirmation' => 'confirmation du mot de passe',
            'diplome' => 'diplôme',
            'date_embauche' => 'date d\'embauche',
            'salaire' => 'salaire',
            'classes' => 'classes',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer et formater les données avant validation
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name)
            ]);
        }

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email))
            ]);
        }

        if ($this->has('diplome')) {
            $this->merge([
                'diplome' => strtolower(trim($this->diplome))
            ]);
        }

        if ($this->has('salaire')) {
            $this->merge([
                'salaire' => (float) $this->salaire
            ]);
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        
        // Log des erreurs de validation pour debugging
        \Log::warning('Validation échouée pour la modification d\'éducateur', [
            'errors' => $errors,
            'admin_id' => auth()->id(),
            'educateur_id' => $this->route('educateur')?->id,
            'input' => $this->except(['password', 'password_confirmation'])
        ]);

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $errors
            ], 422)
        );
    }

    /**
     * Get validated data with only filled fields.
     */
    public function validatedFilled(): array
    {
        return array_filter($this->validated(), function($value, $key) {
            return $this->filled($key);
        }, ARRAY_FILTER_USE_BOTH);
    }
}