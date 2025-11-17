<?php
namespace App\Services;

use App\Models\{Paiement, Inscription, ParentModel, Enfant, User};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentHousekeepingService
{
    /** Marque 'expire' et applique tes règles de suppression */
    public function expireAndCleanup(Paiement $p): void
    {
        DB::transaction(function () use ($p) {
            // 1) Marquer expiré (si pas déjà)
            if ($p->statut !== 'expire') {
                $p->update([
                    'statut'        => 'expire',
                    'date_paiement' => null,
                ]);
            }

            /** @var Inscription $i */
            $i = $p->inscription()->lockForUpdate()->first();

           
if (!$i->parent_id) {
    $i->paiements()->delete();
    $i->delete();
    return;
}

$parent = ParentModel::lockForUpdate()->find($i->parent_id);

// Retrouver l’enfant via les champs de l'inscription
$enfant = Enfant::where('nom', $i->nom_enfant)
    ->where('prenom', $i->prenom_enfant)
    ->whereDate('date_naissance', $i->date_naissance_enfant)
    ->first();

// Supprimer tous les paiements de CETTE inscription
$i->paiements()->delete();

// Détacher + supprimer l’enfant si trouvé
if ($enfant) {
    $parent->enfants()->detach($enfant->id);
    $enfant->delete();
}

// Supprimer l'inscription
$i->delete();

// Vérifier s'il reste des paiements payés pour ce parent
$hasOtherPaid = Paiement::where('parent_id', $parent->id)
    ->where('statut', 'paye')
    ->exists();

if (!$hasOtherPaid) {
    $parent->enfants()->detach();
    if ($parent->user_id && ($user = User::find($parent->user_id))) {
        $user->delete(); 
    }
    $parent->delete();
}

        });
    }

    public function massExpireOverdue(): int
    {
        $now = Carbon::now();
        $targets = Paiement::where('statut', 'en_attente')
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<', $now->toDateString())
            ->get();

        foreach ($targets as $p) {
            $this->expireAndCleanup($p);
        }
        return $targets->count();
    }
}
