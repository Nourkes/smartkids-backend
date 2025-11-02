<?php
// app/Http/Controllers/PublicPaymentController.php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Services\InscriptionFlowService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicPaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    private function isInvalid(Paiement $p): bool
    {
        return !$p->public_token
            || $p->statut !== 'en_attente'
            || ($p->public_token_expires_at && now()->gt($p->public_token_expires_at))
            || $p->consumed_at !== null;
    }

    /**
     * GET /api/public/payments/{token}/quote
     * 🔥 Calcule le montant en temps réel (au moment de la consultation)
     */
public function quote(string $token)
{
    $p = Paiement::with('inscription')->where('public_token', $token)->firstOrFail();
    abort_if($this->isInvalid($p), 410, 'Lien expiré ou utilisé.');

    $quote = null;
    $montantActuel = $p->montant;
$isFirstMonth = false;

if ($p->inscription && $p->plan === 'mensuel' && in_array($p->type, ['inscription','scolarite'], true)) {
    $quote = $this->paymentService->quoteFirstMonthProrata($p->inscription, now());
    $montantActuel = $quote['montant_du'];
    $isFirstMonth = true;
}

    // 🔥 Tarifs (valeurs figées venant de la logique mensuelle, donc alignées config)
    $moisParAnnee = (int) config('smartkids.mois_par_annee', 9);
    $mensuelPlein = null;

    if ($p->inscription) {
        try {
            $mQ = $this->paymentService->quoteMonthly($p->inscription, now());
            $mensuelPlein = (float) $mQ['montant_du']; // plein mensuel (config/classe)
        } catch (\Throwable $e) {
            // fallback: si on n'arrive pas à calculer, on tente via $quote_details
            $mensuelPlein = isset($quote['montant_mensuel']) ? (float) $quote['montant_mensuel'] : null;
        }
    }

    $pricing = [
        'mensuel'       => $mensuelPlein,                         // ex: 300
        'trimestriel'   => $mensuelPlein ? $mensuelPlein * 3 : null,  // 3×
        'annee_total'   => $mensuelPlein ? $mensuelPlein * $moisParAnnee : null, // optionnel
        'mois_par_annee'=> $moisParAnnee,
    ];

    return response()->json([
        'type'               => $p->type,
        'plan'               => $p->plan,
        'montant_du'         => (float) $montantActuel,
        'montant_initial'    => (float) $p->montant,
        'date_echeance'      => optional($p->date_echeance)->toDateString(),
        'periodes_couvertes' => $quote ? [$quote['periode_index']] : $p->periodes_couvertes,
        'inscription'        => [
            'id'             => $p->inscription_id,
            'niveau'         => $p->inscription?->niveau_souhaite,
            'annee_scolaire' => $p->inscription?->annee_scolaire,
        ],
        'quote_details'      => $quote,   // infos techniques (prorata, période…)
        'pricing'            => $pricing, // 🔥 nouveaux champs pour WelcomeScreen
    ]);
}


    /**
     * POST /api/public/payments/{token}/confirm
     * 🔥 Le montant sera calculé dans finalizeAfterFirstMonthPayment()
     */
    public function confirm(Request $r, string $token, InscriptionFlowService $flow)
    {
        $r->validate([
            'methode'   => 'nullable|in:cash,carte,en_ligne',
            'reference' => 'nullable|string',
            'remarques' => 'nullable|string',
        ]);

        /** @var Paiement $p */
        $p = Paiement::where('public_token', $token)->lockForUpdate()->firstOrFail();
        abort_if($this->isInvalid($p), 410, 'Lien expiré ou utilisé.');

        // Vérifier l'échéance
        if ($p->date_echeance && Carbon::parse($p->date_echeance)->endOfDay()->lt(now())) {
            app(\App\Services\PaymentHousekeepingService::class)->expireAndCleanup($p);
            return response()->json(['success'=>false, 'message'=>'Lien expiré.'], 410);
        }
        

      try {
        // Montant recalculé dans finalizeAfterFirstMonthPayment()
        $res = $flow->simulatePayById(
            $p->id,
            'paye',
            $r->input('methode','en_ligne'),
            null,
            $r->input('reference'),
            $r->input('remarques')
        );
    } catch (\Throwable $e) {
        \Log::error('Confirm payment failed', [
            'pid'   => $p->id,
            'error' => $e->getMessage(),
        ]);
        return response()->json(['success'=>false, 'error'=>$e->getMessage()], 500);
    }

        // Consommer le token (one-time)
        $p->update([
            'public_token'            => null,
            'public_token_expires_at' => null,
            'consumed_at'             => now(),
        ]);

        return response()->json(['success'=>true, 'data'=>$res]);
    }
}