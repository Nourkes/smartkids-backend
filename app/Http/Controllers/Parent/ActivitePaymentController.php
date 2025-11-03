<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\{Activite, ParticipationActivite, Paiement};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivitePaymentController extends Controller
{
    /**
     * GET /api/parent/activites/{activite}/payment/quote
     * Renvoie le montant à afficher dans PaymentScreen.
     */
public function quote(Request $request, Activite $activite)
{
    // 🔍 LOG CRITIQUE - Voir tout ce qui arrive
    \Log::info('=== QUOTE REQUEST DEBUG ===', [
        'activite_id' => $activite->id,
        'query_all'   => $request->query(),
        'enfant_id'   => $request->query('enfant_id'),
        'full_url'    => $request->fullUrl(),
        'method'      => $request->method(),
        'headers'     => $request->headers->all(),
    ]);

    try {
        $enfantId = $request->query('enfant_id');
        
        if (empty($enfantId)) {
            \Log::warning('❌ enfant_id est vide', [
                'query' => $request->query(),
                'all'   => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => "L'ID de l'enfant est requis.",
                'errors'  => [
                    'enfant_id' => ["Le paramètre enfant_id est obligatoire."]
                ],
                'debug' => [
                    'received_query' => $request->query(),
                    'full_url' => $request->fullUrl(),
                ]
            ], 400);
        }

        $enfantId = (int) $enfantId;
        
        if ($enfantId <= 0) {
            return response()->json([
                'success' => false,
                'message' => "L'ID de l'enfant est invalide.",
            ], 400);
        }

        $montant = (float) ($activite->prix ?? 0);

        if ($montant <= 0) {
            return response()->json([
                'success' => false,
                'message' => "Cette activité est gratuite ou le prix est invalide.",
            ], 400);
        }

        \Log::info('✅ Quote généré avec succès', [
            'activite_id' => $activite->id,
            'enfant_id'   => $enfantId,
            'montant'     => $montant,
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'Devis généré.',
            'plan'            => 'activite',
            'is_first_month'  => false,
            'montant_du'      => $montant,
            'pricing'         => ['mensuel' => $montant],
            'quote_details'   => ['montant_mensuel' => $montant],
        ], 200);

    } catch (\Throwable $e) {
        \Log::error('❌ Quote error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la génération du devis.',
        ], 500);
    }
}



    /**
     * POST /api/parent/activites/{activite}/payment/confirm?enfant_id=XX
     * Valide le paiement (crée la participation et/ou le paiement si besoin).
     * Le PaymentScreen enverra body: { methode: 'en_ligne' }.
     */
    public function confirm(Request $request, Activite $activite)
    {
        $data = $request->validate([
            'methode'   => 'nullable|in:en_ligne,carte,cash',
            'reference' => 'nullable|string|max:255',
            'remarques' => 'nullable|string',
        ]);

        // enfant_id est porté dans la query du confirmUrl (…/confirm?enfant_id=123)
        $enfantId = (int) $request->query('enfant_id', 0);
        if ($enfantId <= 0) {
            throw ValidationException::withMessages(['enfant_id' => 'enfant_id est requis.']);
        }

        $parentId = optional($request->user()?->parent)->id;

        return DB::transaction(function () use ($activite, $enfantId, $parentId, $data) {
            // 1) S’assurer qu’il existe une participation
            $participation = ParticipationActivite::where('activite_id', $activite->id)
                ->where('enfant_id', $enfantId)
                ->first();

            if (! $participation) {
                // auto-inscrire si pas encore inscrit
                $activite->enfants()->attach($enfantId, [
                    'statut_participation' => 'inscrit',
                    'date_inscription'     => now(),
                ]);

                $participation = ParticipationActivite::where('activite_id', $activite->id)
                    ->where('enfant_id', $enfantId)
                    ->first();
            }

            $montant = (float) ($activite->prix ?? 0);

            // 2) Chercher un paiement en_attente sinon créer
            $paiement = Paiement::where('participation_activite_id', $participation->id)
                ->where('statut', 'en_attente')
                ->first();

            if (! $paiement) {
                $paiement = Paiement::create([
                    'parent_id'                 => $parentId,
                    'participation_activite_id' => $participation->id,
                    'montant'                   => $montant,
                    'type'                      => 'activite',
                    'methode_paiement'          => $data['methode'] ?? 'en_ligne',
                    'date_echeance'             => now(),
                    'statut'                    => 'en_attente',
                    'remarques'                 => $data['remarques'] ?? null,
                    'reference_transaction'     => $data['reference'] ?? null,
                ]);
            }

            // 3) Valider le paiement
            $paiement->update([
                'statut'                => 'paye',
                'date_paiement'         => now(),
                'methode_paiement'      => $data['methode'] ?? 'en_ligne',
                'reference_transaction' => $data['reference'] ?? $paiement->reference_transaction,
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Paiement confirmé',
                'paiement' => $paiement,
            ]);
        });
    }
}
