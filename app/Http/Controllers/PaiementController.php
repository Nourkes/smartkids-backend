<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimulatePaymentRequest;
use App\Http\Resources\InscriptionResource;
use App\Http\Resources\PaiementResource;
use App\Models\Paiement;
use App\Services\InscriptionFlowService;

class PaiementController extends Controller
{
    public function __construct(private InscriptionFlowService $flow) {}

    // POST /api/paiements/{paiement}/simulate
    public function simulateById(SimulatePaymentRequest $request, Paiement $paiement)
    {
        $res = $this->flow->simulatePayById(
            $paiement->id,
            $request->input('action', 'paye'),
            $request->input('methode_paiement'),
            $request->input('montant'),
            $request->input('reference_transaction'),
            $request->input('remarques')
        );

        $msg = $res['expired']
            ? 'Paiement expiré : inscription rejetée.'
            : ($request->input('action') === 'annule'
                ? 'Paiement annulé.'
                : 'Paiement reçu. Compte parent/enfant finalisé et inscription confirmée.');

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data'    => [
                'inscription' => new InscriptionResource($res['inscription']),
                'paiement'    => new PaiementResource($res['paiement']),
            ],
        ]);
    }
}
