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
    public function __construct(private PaymentService $svc) {}

    public function firstMonthQuote(Request $request, Inscription $inscription)
    {
        $date  = $request->query('pay_date', now()->toDateString());
        $quote = $this->svc->quoteFirstMonthProrata($inscription, Carbon::parse($date));
        return response()->json($quote);
    }

    public function firstMonthConfirm(Request $request, Inscription $inscription)
    {
        $validated = $request->validate([
            'pay_date' => ['nullable', 'date'],
            'methode'  => ['nullable', 'in:cash,carte,en_ligne'],
        ]);
        $payDate = isset($validated['pay_date']) ? Carbon::parse($validated['pay_date']) : now();

        $quote = $this->svc->quoteFirstMonthProrata($inscription, $payDate);
        $p     = $this->svc->createFirstMonthPayment($inscription, $quote, $validated['methode'] ?? 'cash');

        return response()->json(['paiement' => $p->fresh()], 201);
    }

    public function monthlyQuote(Request $request, Inscription $inscription)
    {
        $date  = $request->query('pay_date', now()->toDateString());
        $quote = $this->svc->quoteMonthly($inscription, Carbon::parse($date));
        return response()->json($quote);
    }

    public function monthlyCreate(Request $request, Inscription $inscription)
    {
        $validated = $request->validate([
            'pay_date' => ['nullable', 'date'],
            'methode'  => ['nullable', 'in:cash,carte,en_ligne'],
        ]);
        $payDate = isset($validated['pay_date']) ? Carbon::parse($validated['pay_date']) : now();

        $quote = $this->svc->quoteMonthly($inscription, $payDate);

        $p = Paiement::create([
            'parent_id'          => $inscription->parent_id,
            'inscription_id'     => $inscription->id,
            'type'               => 'scolarite',
            'plan'               => 'mensuel',
            'periodes_couvertes' => [$quote['periode_index']],
            'montant'            => $quote['montant_du'],
            'methode_paiement'   => $validated['methode'] ?? 'cash',
            'date_echeance'      => $quote['echeance'],
            'statut'             => 'en_attente',
        ]);

        return response()->json(['paiement' => $p->fresh()], 201);
    }
}
