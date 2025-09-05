<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreParentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        // Seuls les admins peuvent créer des parents
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        return [
            // Données utilisateur obligatoires
            'nom' => 'required|string|max:255|regex:/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
            'prenom' => 'required|string|max:255|regex:/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
            'email' => 'required|email:rfc,dns|unique:users,email|max:255',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            
            // Données parent
            'profession' => 'nullable|string|max:255',
            'telephone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(\+216|00216|216)?[0-9\s\-\.]{8,}$/' // Format tunisien
            ],
            'adresse' => 'nullable|string|max:500',
            'contact_urgence_nom' => 'nullable|string|max:255|regex:/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
            'contact_urgence_telephone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^(\+216|00216|216)?[0-9\s\-\.]{8,}$/'
            ],
            
            // Enfants (optionnel) - Relations
            'enfants' => 'nullable|array|max:10', // Maximum 10 enfants
            'enfants.*' => 'integer|exists:enfant,id|distinct',
            
            // Statut initial (optionnel, par défaut 'actif')
            'statut' => 'nullable|in:actif,inactif,suspendu'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages()
    {
        return [
            // Messages pour les champs utilisateur
            'nom.required' => 'Le nom est obligatoire.',
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'nom.regex' => 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.',
            
            'prenom.required' => 'Le prénom est obligatoire.',
            'prenom.string' => 'Le prénom doit être une chaîne de caractères.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'prenom.regex' => 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.',
            
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'email.max' => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
            
            // Messages pour les champs parent
            'profession.string' => 'La profession doit être une chaîne de caractères.',
            'profession.max' => 'La profession ne peut pas dépasser 255 caractères.',
            
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.string' => 'Le téléphone doit être une chaîne de caractères.',
            'telephone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères.',
            'telephone.regex' => 'Le format du numéro de téléphone n\'est pas valide. Utilisez le format tunisien (+216 XX XXX XXX).',
            
            'adresse.string' => 'L\'adresse doit être une chaîne de caractères.',
            'adresse.max' => 'L\'adresse ne peut pas dépasser 500 caractères.',
            
            'contact_urgence_nom.string' => 'Le nom du contact d\'urgence doit être une chaîne de caractères.',
            'contact_urgence_nom.max' => 'Le nom du contact d\'urgence ne peut pas dépasser 255 caractères.',
            'contact_urgence_nom.regex' => 'Le nom du contact d\'urgence ne peut contenir que des lettres, espaces, tirets et apostrophes.',
            
            'contact_urgence_telephone.string' => 'Le téléphone du contact d\'urgence doit être une chaîne de caractères.',
            'contact_urgence_telephone.max' => 'Le téléphone du contact d\'urgence ne peut pas dépasser 20 caractères.',
            'contact_urgence_telephone.regex' => 'Le format du téléphone du contact d\'urgence n\'est pas valide.',
            
            // Messages pour les enfants
            'enfants.array' => 'Les enfants doivent être fournis sous forme de tableau.',
            'enfants.max' => 'Un parent ne peut pas avoir plus de 10 enfants associés.',
            'enfants.*.integer' => 'Chaque ID d\'enfant doit être un nombre entier.',
            'enfants.*.exists' => 'Un ou plusieurs enfants spécifiés n\'existent pas.',
            'enfants.*.distinct' => 'Vous ne pouvez pas associer le même enfant plusieurs fois.',
            
            // Messages pour le statut
            'statut.in' => 'Le statut doit être actif, inactif ou suspendu.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes()
    {
        return [
            'nom' => 'nom',
            'prenom' => 'prénom',
            'email' => 'adresse email',
            'password' => 'mot de passe',
            'profession' => 'profession',
            'telephone' => 'numéro de téléphone',
            'adresse' => 'adresse',
            'contact_urgence_nom' => 'nom du contact d\'urgence',
            'contact_urgence_telephone' => 'téléphone du contact d\'urgence',
            'enfants' => 'enfants',
            'statut' => 'statut',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validation personnalisée supplémentaire
            
            // Vérifier si le nom et prénom ne sont pas identiques
            if ($this->nom && $this->prenom && strtolower($this->nom) === strtolower($this->prenom)) {
                $validator->errors()->add('prenom', 'Le prénom ne peut pas être identique au nom.');
            }
            
            // Vérifier que le téléphone d'urgence est différent du téléphone principal
            if ($this->telephone && $this->contact_urgence_telephone) {
                $tel1 = preg_replace('/[^0-9]/', '', $this->telephone);
                $tel2 = preg_replace('/[^0-9]/', '', $this->contact_urgence_telephone);
                
                if ($tel1 === $tel2) {
                    $validator->errors()->add('contact_urgence_telephone', 
                        'Le téléphone du contact d\'urgence doit être différent du téléphone principal.');
                }
            }
            
            // Si un nom de contact d'urgence est fourni, le téléphone doit l'être aussi
            if ($this->contact_urgence_nom && !$this->contact_urgence_telephone) {
                $validator->errors()->add('contact_urgence_telephone', 
                    'Le téléphone du contact d\'urgence est requis quand un nom est fourni.');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Nettoyer et formater les données avant validation
        $this->merge([
            'nom' => $this->nom ? trim(ucwords(strtolower($this->nom))) : null,
            'prenom' => $this->prenom ? trim(ucwords(strtolower($this->prenom))) : null,
            'email' => $this->email ? trim(strtolower($this->email)) : null,
            'profession' => $this->profession ? trim($this->profession) : null,
            'telephone' => $this->telephone ? preg_replace('/\s+/', ' ', trim($this->telephone)) : null,
            'adresse' => $this->adresse ? trim($this->adresse) : null,
            'contact_urgence_nom' => $this->contact_urgence_nom ? trim(ucwords(strtolower($this->contact_urgence_nom))) : null,
            'contact_urgence_telephone' => $this->contact_urgence_telephone ? preg_replace('/\s+/', ' ', trim($this->contact_urgence_telephone)) : null,
            'statut' => $this->statut ?? 'actif', // Statut par défaut
        ]);
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Les données fournies ne sont pas valides.',
            'errors' => $validator->errors(),
            'error_count' => $validator->errors()->count()
        ], 422);

        throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
    }
}