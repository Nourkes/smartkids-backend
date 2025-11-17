<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnfantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'date_naissance' => 'required|date|before:today|after:1900-01-01',
            'lieu_naissance' => 'nullable|string|max:255',
            'sexe' => ['required', Rule::in(['garçon', 'fille'])],

            'numero_identification' => 'nullable|string|unique:enfant,numero_identification|max:50',
            'adresse' => 'nullable|string|max:1000',
            'telephone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'email' => 'nullable|email|max:255',
            
            // Informations médicales
            'allergies' => 'nullable|string|max:1000',
            'medicaments' => 'nullable|string|max:1000',
            'problemes_sante' => 'nullable|string|max:1000',
            'medecin_nom' => 'nullable|string|max:255',
            'medecin_telephone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            
            // Relations
            'groupe_id' => 'nullable|exists:groupe,id',
            'parents' => 'required|array|min:1',
            'parents.*' => 'exists:parents,id',
            
            // Statut
            'statut' => 'sometimes|in:actif,inactif,suspendu',
            'notes_admin' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'prenom.required' => 'Le prénom est obligatoire.',
            'nom.required' => 'Le nom est obligatoire.',
            'date_naissance.required' => 'La date de naissance est obligatoire.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'date_naissance.after' => 'La date de naissance ne peut pas être antérieure à 1900.',
            'sexe.required' => 'Le sexe est obligatoire.',
            'sexe.in' => 'Le sexe doit être M (Masculin) ou F (Féminin).',
            'numero_identification.unique' => 'Ce numéro d\'identification existe déjà.',
            'telephone.regex' => 'Le format du numéro de téléphone est invalide.',
            'email.email' => 'L\'adresse email doit être valide.',
            'groupe_id.exists' => 'Le groupe sélectionné n\'existe pas.',
            'parents.required' => 'Au moins un parent doit être associé.',
            'parents.min' => 'Au moins un parent doit être sélectionné.',
            'parents.*.exists' => 'Un des parents sélectionnés n\'existe pas.',
            'medecin_telephone.regex' => 'Le format du numéro de téléphone du médecin est invalide.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'prenom' => 'prénom',
            'nom' => 'nom',
            'date_naissance' => 'date de naissance',
            'lieu_naissance' => 'lieu de naissance',
            'sexe' => 'sexe',
            'numero_identification' => 'numéro d\'identification',
            'adresse' => 'adresse',
            'telephone' => 'téléphone',
            'email' => 'email',
            'allergies' => 'allergies',
            'medicaments' => 'médicaments',
            'problemes_sante' => 'problèmes de santé',
            'medecin_nom' => 'nom du médecin',
            'medecin_telephone' => 'téléphone du médecin',
            'groupe_id' => 'groupe',
            'parents' => 'parents',
            'statut' => 'statut',
            'notes_admin' => 'notes administrateur',
        ];
    }
}

class UpdateEnfantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $enfantId = $this->route('enfant')->id ?? null;
        
        return [
            'prenom' => 'sometimes|string|max:255',
            'nom' => 'sometimes|string|max:255',
            'date_naissance' => 'sometimes|date|before:today|after:1900-01-01',
            'lieu_naissance' => 'nullable|string|max:255',
            'sexe' => 'sometimes|in:M,F',
            'numero_identification' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('enfant', 'numero_identification')->ignore($enfantId),
            ],
            'adresse' => 'nullable|string|max:1000',
            'telephone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'email' => 'nullable|email|max:255',
            
            // Informations médicales
            'allergies' => 'nullable|string|max:1000',
            'medicaments' => 'nullable|string|max:1000',
            'problemes_sante' => 'nullable|string|max:1000',
            'medecin_nom' => 'nullable|string|max:255',
            'medecin_telephone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            
            // Relations
            'groupe_id' => 'nullable|exists:groupe,id',
            'parents' => 'sometimes|array|min:1',
            'parents.*' => 'exists:parents,id',
            
            // Statut
            'statut' => 'sometimes|in:actif,inactif,suspendu',
            'notes_admin' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'date_naissance.after' => 'La date de naissance ne peut pas être antérieure à 1900.',
            'sexe.in' => 'Le sexe doit être M (Masculin) ou F (Féminin).',
            'numero_identification.unique' => 'Ce numéro d\'identification existe déjà.',
            'telephone.regex' => 'Le format du numéro de téléphone est invalide.',
            'email.email' => 'L\'adresse email doit être valide.',
            'groupe_id.exists' => 'Le groupe sélectionné n\'existe pas.',
            'parents.min' => 'Au moins un parent doit être sélectionné.',
            'parents.*.exists' => 'Un des parents sélectionnés n\'existe pas.',
            'medecin_telephone.regex' => 'Le format du numéro de téléphone du médecin est invalide.',
        ];
    }
}

class ChangeStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'statut' => 'required|in:actif,inactif,suspendu',
            'notes_admin' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'statut.required' => 'Le statut est obligatoire.',
            'statut.in' => 'Le statut doit être: actif, inactif ou suspendu.',
        ];
    }
}

class UpdateMedicalInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'allergies' => 'nullable|string|max:1000',
            'medicaments' => 'nullable|string|max:1000',
            'problemes_sante' => 'nullable|string|max:1000',
            'medecin_nom' => 'nullable|string|max:255',
            'medecin_telephone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'medecin_telephone.regex' => 'Le format du numéro de téléphone du médecin est invalide.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'allergies' => 'allergies',
            'medicaments' => 'médicaments',
            'problemes_sante' => 'problèmes de santé',
            'medecin_nom' => 'nom du médecin',
            'medecin_telephone' => 'téléphone du médecin',
        ];
    }
}