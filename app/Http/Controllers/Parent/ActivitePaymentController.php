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
     * GET /api/parent/activites/{activite}/payment/quote?enfant_id=XX
     */
    public function quote(Request $request, Activite $activite)
    {
        $enfantId = (int) $request->query('enfant_id', 0);
        if ($enfantId <= 0) {
            return response()->json([
                'success' => false,
                'message' => "Le paramètre enfant_id est obligatoire.",
            ], 400);
        }

        $montant = (float) ($activite->prix ?? 0);
        if ($montant <= 0) {
            return response()->json([
                'success' => false,
                'message' => "Cette activité est gratuite ou le prix est invalide.",
            ], 400);
        }

        // ✅ Si déjà inscrit + paiement déjà payé => bloquer
        $participation = ParticipationActivite::where('activite_id', $activite->id)
            ->where('enfant_id', $enfantId)
            ->first();

        if ($participation) {
            $alreadyPaid = Paiement::where('participation_activite_id', $participation->id)
                ->where('statut', 'paye')
                ->exists();

            if ($alreadyPaid) {
                return response()->json([
                    'success' => false,
                    'message' => "L'enfant est déjà inscrit à cette activité.",
                ], 409);
            }
        }

        // ✅ Sinon: OK -> quote normal
        return response()->json([
            'success'         => true,
            'message'         => 'Devis généré.',
            'plan'            => 'activite',
            'is_first_month'  => false,
            'montant_du'      => $montant,
            'pricing'         => ['mensuel' => $montant],
            'quote_details'   => ['montant_mensuel' => $montant],
        ], 200);
    }

    /**
     * POST /api/parent/activites/{activite}/payment/confirm?enfant_id=XX
     */
    public function confirm(Request $request, Activite $activite)
    {
        $data = $request->validate([
            'methode'   => 'nullable|in:en_ligne,carte,cash',
            'reference' => 'nullable|string|max:255',
            'remarques' => 'nullable|string',
        ]);

        $enfantId = (int) $request->query('enfant_id', 0);
        if ($enfantId <= 0) {
            throw ValidationException::withMessages(['enfant_id' => 'enfant_id est requis.']);
        }

        $parentId = optional($request->user()?->parent)->id;

        return DB::transaction(function () use ($activite, $enfantId, $parentId, $data) {

            // 1) récupérer / créer participation
            $participation = ParticipationActivite::where('activite_id', $activite->id)
                ->where('enfant_id', $enfantId)
                ->lockForUpdate()
                ->first();

            if (! $participation) {
                $activite->enfants()->attach($enfantId, [
                    'statut_participation' => 'inscrit',
                    'date_inscription'     => now(),
                ]);

                $participation = ParticipationActivite::where('activite_id', $activite->id)
                    ->where('enfant_id', $enfantId)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $participation) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de créer la participation.",
                ], 500);
            }

            // ✅ 2) Si déjà payé => STOP (ne jamais recréer un paiement)
            $alreadyPaid = Paiement::where('participation_activite_id', $participation->id)
                ->where('statut', 'paye')
                ->lockForUpdate()
                ->exists();

            if ($alreadyPaid) {
                return response()->json([
                    'success' => false,
                    'message' => "Déjà inscrit(e) à cette activité.",
                ], 409);
            }

            $montant = (float) ($activite->prix ?? 0);

            // 3) paiement en attente existant ? sinon créer
            $paiement = Paiement::where('participation_activite_id', $participation->id)
                ->where('statut', 'en_attente')
                ->lockForUpdate()
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

            // 4) valider
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
            ], 200);
        });
    }
}
