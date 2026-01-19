<?php
// app/Services/ActiviteParticipationService.php

namespace App\Services;

use App\Models\{Activite, ParticipationActivite, Paiement, ParentModel};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActiviteParticipationService
{public function dejaInscrit(Activite $activite, int $enfantId): bool
{
    return ParticipationActivite::where('activite_id', $activite->id)
        ->where('enfant_id', $enfantId)
        ->exists();
}

    /**
     * Inscrire un enfant à une activité (et créer un paiement si prix > 0).
     * Ne modifie AUCUN de tes modèles existants.
     */
    public function inscrire(
        Activite $activite,
        int $enfantId,
        ?int $parentId = null,
        ?string $remarques = null,
        ?string $methodePaiement = null // 'cash' | 'carte' | 'en_ligne'
    ): array {
        // 1) Capacité
        if ($activite->capacite_max && $activite->enfants()->count() >= $activite->capacite_max) {
            throw ValidationException::withMessages(['capacite' => 'Capacité maximale atteinte']);
        }

        // 2) Déjà inscrit ?
        if ($activite->enfants()->where('enfant_id', $enfantId)->exists()) {
            throw ValidationException::withMessages(['enfant_id' => "L'enfant est déjà inscrit à cette activité"]);
        }

        return DB::transaction(function () use ($activite, $enfantId, $parentId, $remarques, $methodePaiement) {
            // 3) Attacher l’enfant sur le pivot
            $activite->enfants()->attach($enfantId, [
                'statut_participation' => 'inscrit',
                'remarques'            => $remarques,
                'date_inscription'     => now(),
            ]);

            // Récupérer la participation (pivot sous forme de modèle si tu l’utilises)
            /** @var ParticipationActivite|null $participation */
            $participation = ParticipationActivite::where([
                'activite_id' => $activite->id,
                'enfant_id'   => $enfantId,
            ])->first();

            $paiement = null;

            // 4) Si activité payante => créer un paiement en attente
            $montant = (float) ($activite->prix ?? 0);
            if ($montant > 0 && $participation) {
                // parent_id optionnel : si fourni on le renseigne
                $paiement = Paiement::create([
                    'parent_id'                 => $parentId, // peut rester null si non dispo
                    'participation_activite_id' => $participation->id,
                    'montant'                   => $montant,
                    'type'                      => 'activite',
                    'methode_paiement'          => $methodePaiement ?: 'cash',
                    'date_echeance'             => $activite->date_activite ?? now()->addDays(7),
                    'statut'                    => 'en_attente', // à régler plus tard
                    'remarques'                 => $remarques,
                ]);
            }

            return [
                'participation' => $participation,
                'paiement'      => $paiement,
            ];
        });
    }

    /**
     * Désinscrire un enfant.
     */
    public function desinscrire(Activite $activite, int $enfantId): void
    {
        if (! $activite->enfants()->where('enfant_id', $enfantId)->exists()) {
            throw ValidationException::withMessages(['enfant_id' => "L'enfant n'est pas inscrit à cette activité"]);
        }

        DB::transaction(function () use ($activite, $enfantId) {
            // Optionnel : annuler/mettre à jour le paiement lié
            if ($p = ParticipationActivite::where('activite_id', $activite->id)->where('enfant_id', $enfantId)->first()) {
                // ex: marquer le paiement comme annulé si encore en attente
                if ($p->paiement && $p->paiement->statut === 'en_attente') {
                    $p->paiement->update(['statut' => 'annule']);
                }
            }

            $activite->enfants()->detach($enfantId);
        });
    }
}
