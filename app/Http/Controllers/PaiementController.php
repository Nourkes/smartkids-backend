<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimulatePaymentRequest;
use App\Http\Resources\{InscriptionResource, PaiementResource};
use App\Models\Paiement;
use App\Services\InscriptionFlowService;
use Illuminate\Http\JsonResponse;

class PaiementController extends Controller
{
    public function __construct(private InscriptionFlowService $flow) {}

    /**
     * POST /api/paiements/{paiement}/simulate
     * Body JSON:
     * {
     *   "action": "paye" | "expire" | "annule",
     *   "methode_paiement": "cash",
     *   "reference_transaction": "TEST-001",
     *   "remarques": "..."
     * }
     */
    // POST /api/paiements/{paiement}/simulate
// app/Http/Controllers/PaiementController.php

public function simulateById(SimulatePaymentRequest $request, int $paiement)
{
    $res = $this->flow->simulatePayById(
        $paiement,
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
            'inscription' => new \App\Http\Resources\InscriptionResource($res['inscription']),
            'paiement'    => new \App\Http\Resources\PaiementResource($res['paiement']),
        ],
    ]);
}

}
