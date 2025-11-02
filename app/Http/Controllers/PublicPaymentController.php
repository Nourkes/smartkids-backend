<?php
namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Services\InscriptionFlowService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicPaymentController extends Controller
{
    private function isInvalid(Paiement $p): bool
    {
        return !$p->public_token
            || $p->statut !== 'en_attente'
            || ($p->public_token_expires_at && now()->gt($p->public_token_expires_at))
            || $p->consumed_at !== null;
    }

    /** GET /api/public/payments/{token}/quote */
    public function quote(string $token)
    {
        $p = Paiement::with('inscription')->where('public_token', $token)->firstOrFail();
        abort_if($this->isInvalid($p), 410, 'Lien expiré ou utilisé.');

        // On renvoie ce qui suffit pour l’UI (pas de méta sensible)
        return response()->json([
            'type'               => $p->type,                 // 'inscription' ou 'scolarite'
            'plan'               => $p->plan,                 // 'mensuel'|'semestre'|'annee' (ou null)
            'montant_du'         => (float) $p->montant,
            'date_echeance'      => optional($p->date_echeance)->toDateString(),
            'periodes_couvertes' => $p->periodes_couvertes,   // ex [3] si déjà fixée
            'inscription' => [
                'id'             => $p->inscription_id,
                'niveau'         => $p->inscription?->niveau_souhaite,
                'annee_scolaire' => $p->inscription?->annee_scolaire,
            ],
        ]);
    }

    /** POST /api/public/payments/{token}/confirm */
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

        // Si l’échéance est passée → expire & clean
        if ($p->date_echeance && Carbon::parse($p->date_echeance)->endOfDay()->lt(now())) {
            app(\App\Services\PaymentHousekeepingService::class)->expireAndCleanup($p);
            return response()->json(['success'=>false, 'message'=>'Lien expiré.'], 410);
        }

        // On réutilise le flux existant
        $res = $flow->simulatePayById(
            $p->id,
            'paye',
            $r->input('methode','en_ligne'),
            $p->montant,
            $r->input('reference'),
            $r->input('remarques')
        );

        // Consommer le token (one-time)
        $p->update([
            'public_token'            => null,
            'public_token_expires_at' => null,
            'consumed_at'             => now(),
        ]);

        return response()->json(['success'=>true, 'data'=>$res]);
    }
}
