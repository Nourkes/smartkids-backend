<?php
// app/Http/Controllers/PaymentQuotesController.php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentQuotesController extends Controller
{
    public function __construct(private PaymentService $svc)
    {
    }

    public function firstMonthQuote(Request $request, Inscription $inscription)
    {
        $date = $request->query('pay_date', now()->toDateString());
        $quote = $this->svc->quoteFirstMonthProrata($inscription, Carbon::parse($date));
        return response()->json($quote);
    }

    public function firstMonthConfirm(Request $request, Inscription $inscription)
    {
        $validated = $request->validate([
            'pay_date' => ['nullable', 'date'],
            'methode' => ['nullable', 'in:cash,carte,en_ligne'],
        ]);
        $payDate = isset($validated['pay_date']) ? Carbon::parse($validated['pay_date']) : now();

        $quote = $this->svc->quoteFirstMonthProrata($inscription, $payDate);
        $p = $this->svc->createFirstMonthPayment($inscription, $quote, $validated['methode'] ?? 'cash');

        return response()->json(['paiement' => $p->fresh()], 201);
    }

    public function monthlyQuote(Request $request, Inscription $inscription)
    {
        $currentYear = config('smartkids.current_academic_year');

        // Vérifier que l'inscription est pour l'année courante
        if ($inscription->annee_scolaire !== $currentYear) {
            return response()->json([
                'error' => 'Cette inscription n\'est pas valide pour l\'année en cours',
                'code' => 'WRONG_ACADEMIC_YEAR',
                'current_year' => $currentYear,
                'inscription_year' => $inscription->annee_scolaire,
            ], 403);
        }

        // Vérifier que l'inscription est acceptée
        if ($inscription->statut !== 'accepted') {
            return response()->json([
                'error' => 'Inscription non validée',
                'code' => 'INSCRIPTION_NOT_ACCEPTED',
            ], 403);
        }

        $date = $request->query('pay_date', now()->toDateString());
        $quote = $this->svc->quoteMonthly($inscription, Carbon::parse($date));
        return response()->json($quote);
    }

    public function monthlyCreate(Request $request, Inscription $inscription)
    {
        $currentYear = config('smartkids.current_academic_year');

        // Vérifier que l'inscription est pour l'année courante
        if ($inscription->annee_scolaire !== $currentYear) {
            return response()->json([
                'error' => 'Vous ne pouvez payer que pour l\'année en cours',
                'message' => 'Veuillez vous réinscrire pour l\'année ' . $currentYear,
                'code' => 'WRONG_ACADEMIC_YEAR',
            ], 403);
        }

        $validated = $request->validate([
            'pay_date' => ['nullable', 'date'],
            'methode' => ['nullable', 'in:cash,carte,en_ligne'],
        ]);
        $payDate = isset($validated['pay_date']) ? Carbon::parse($validated['pay_date']) : now();

        $quote = $this->svc->quoteMonthly($inscription, $payDate);

        $p = Paiement::create([
            'parent_id' => $inscription->parent_id,
            'inscription_id' => $inscription->id,
            'type' => 'scolarite',
            'plan' => 'mensuel',
            'periodes_couvertes' => [$quote['periode_index']],
            'montant' => $quote['montant_du'],
            'methode_paiement' => $validated['methode'] ?? 'cash',
            'date_echeance' => $quote['echeance'],
            'statut' => 'en_attente',
        ]);

        return response()->json(['paiement' => $p->fresh()], 201);
    }
}
