<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateParentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $parent = $this->route('parent');
        return $this->user()->can('update', $parent);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $parent = $this->route('parent');
        
        return [
            'nom' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-ZÀ-ÿ\s\'-]+$/'
            ],
            'prenom' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-ZÀ-ÿ\s\'-]+$/'
            ],
            'email' => [
                'required',
                'email:rfc,dns',
                Rule::unique('users', 'email')->ignore($parent->user_id),
                'max:255'
            ],
            'password' => [
                'nullable',
                'sometimes',
                Password::min(8)
                    ->letters()
                    ->numbers()
                    ->mixedCase()
                    ->uncompromised()
            ],
            'telephone' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]{8,20}$/'
            ],
            'adresse' => [
                'required',
                'string',
                'max:500'
            ],
            'profession' => [
                'nullable',
                'string',
                'max:255'
            ],
            'contact_urgence_nom' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-ZÀ-ÿ\s\'-]+$/'
            ],
            'contact_urgence_telephone' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]{8,20}$/',
                'different:telephone'
            ],
            'statut' => [
                'sometimes',
                'in:actif,inactif,suspendu'
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nom.regex' => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et traits d\'union.',
            'prenom.regex' => 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et traits d\'union.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'telephone.regex' => 'Le format du numéro de téléphone n\'est pas valide.',
            'contact_urgence_nom.regex' => 'Le nom du contact d\'urgence ne peut contenir que des lettres, espaces, apostrophes et traits d\'union.',
            'contact_urgence_telephone.regex' => 'Le format du téléphone du contact d\'urgence n\'est pas valide.',
            'contact_urgence_telephone.different' => 'Le téléphone du contact d\'urgence doit être différent du téléphone principal.',
            'statut.in' => 'Le statut doit être : actif, inactif ou suspendu.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nom' => 'nom',
            'prenom' => 'prénom',
            'email' => 'adresse email',
            'password' => 'mot de passe',
            'telephone' => 'numéro de téléphone',
            'adresse' => 'adresse',
            'profession' => 'profession',
            'contact_urgence_nom' => 'nom du contact d\'urgence',
            'contact_urgence_telephone' => 'téléphone du contact d\'urgence',
            'statut' => 'statut',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Vérification supplémentaire : un parent ne peut pas modifier son propre statut
            if ($this->has('statut') && $this->user()->type_utilisateur === 'parent') {
                $validator->errors()->add('statut', 'Vous ne pouvez pas modifier votre propre statut.');
            }
        });
    }
}