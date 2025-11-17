<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimulatePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; } 

    public function rules(): array
    {
        return [
            'methode_paiement' => 'required|in:cash,carte,virement',
            'montant'          => 'nullable|numeric|min:0',
            'remarques'        => 'nullable|string|max:500',
        ];
    }
}
