<?php

namespace App\Services;

use App\Models\Inscription;
use App\Models\InscriptionForm;
use App\Models\Paiement;
use App\Models\User;
use App\Models\ParentModel;
use App\Models\Enfant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
/**
 * Orchestration du flux d'inscription:
 * - dépôt (public)
 * - décision admin (accepter / attente / refuser)
 * - création du paiement en base (virtuel)
 * - simulation du paiement (paye / expire / annule)
 * - finalisation (création user + parent + enfant)
 */
class InscriptionFlowService
{
    /** Méthodes de paiement autorisées (selon ton schéma DB) */
    private const PAIEMENT_METHODS = ['cash', 'carte', 'en_ligne'];

    /** Nb de jours avant échéance paiement (fallback: 3) */
    private function echeanceJours(): int
    {
        return (int) config('school.paiement_echeance_jours', 3);
    }

    /** Normalise la méthode de paiement */
    private function normalizePaymentMethod(?string $m): string
    {
        $m = strtolower((string) $m);
        return in_array($m, self::PAIEMENT_METHODS, true) ? $m : 'cash';
    }

    /**
     * Dépôt public d'une demande d'inscription.
     */
    public function create(array $data): Inscription
    {
        return DB::transaction(function () use ($data) {
            $form = InscriptionForm::create(['payload' => $data]);

            return Inscription::create([
                'form_id'                   => $form->id,
                'niveau_souhaite'           => $data['niveau_souhaite'],
                'annee_scolaire'            => $data['annee_scolaire'],
                'date_inscription'          => now(),
                'statut'                    => 'pending',

                // Parent
                'nom_parent'                => $data['nom_parent'],
                'prenom_parent'             => $data['prenom_parent'],
                'email_parent'              => $data['email_parent'],
                'telephone_parent'          => $data['telephone_parent'],
                'adresse_parent'            => $data['adresse_parent'] ?? null,
                'profession_parent'         => $data['profession_parent'] ?? null,

                // Enfant
                'nom_enfant'                => $data['nom_enfant'],
                'prenom_enfant'             => $data['prenom_enfant'],
                'date_naissance_enfant'     => $data['date_naissance_enfant'],
                'genre_enfant'              => $data['genre_enfant'] ?? null,

                // Santé / docs (peuvent être array -> stockés tels quels dans inscriptions)
                'problemes_sante'           => $data['problemes_sante'] ?? null,
                'allergies'                 => $data['allergies'] ?? null,
                'medicaments'               => $data['medicaments'] ?? null,
                'documents_fournis'         => $data['documents_fournis'] ?? null,

                // Urgence
                'contact_urgence_nom'       => $data['contact_urgence_nom'] ?? null,
                'contact_urgence_telephone' => $data['contact_urgence_telephone'] ?? null,

                'remarques'                 => $data['remarques'] ?? null,
            ]);
        });
    }

    /** Wrappers pratiques (alias des routes /accept, /wait, /reject) */
    public function accept(
        Inscription $i,
        ?int $classeId,
        int $adminId,
        ?float $fraisInscription = null,
        ?float $fraisMensuel = null,
        ?string $remarques = null,
        string $methodePaiement = 'cash'
    ): array {
        return $this->decide(
            $i,
            'accepter',
            $classeId,
            $adminId,
            $fraisInscription,
            $fraisMensuel,
            $remarques,
            $methodePaiement
        );
    }

    public function wait(Inscription $i, int $adminId, ?string $remarques = null): array
    {
        return $this->decide($i, 'mettre_en_attente', null, $adminId, null, null, $remarques);
    }

    public function reject(Inscription $i, int $adminId, ?string $remarques = null): array
    {
        return $this->decide($i, 'refuser', null, $adminId, null, null, $remarques);
    }

    /**
     * Décision admin.
     * Retour: ['inscription' => Inscription, 'paiement' => ?Paiement]
     */
    public function decide(
        Inscription $i,
        string $action,                    // 'accepter' | 'mettre_en_attente' | 'refuser'
        ?int $classeId,
        int $adminId,
        ?float $fraisInscription = null,
        ?float $fraisMensuel = null,       // conservé pour évolutivité
        ?string $remarques = null,
        string $methodePaiement = 'cash'
    ): array {
        $paiement = null;

        return DB::transaction(function () use (&$paiement, $i, $action, $classeId, $adminId, $fraisInscription, $remarques, $methodePaiement) {

            // REFUSER
            if ($action === 'refuser') {
                $i->update([
                    'statut'               => 'rejected',
                    'position_attente'     => null,
                    'classe_id'            => null,
                    'remarques_admin'      => $remarques,
                    'traite_par_admin_id'  => $adminId,
                    'date_traitement'      => now(),
                ]);

                return ['inscription' => $i->fresh(), 'paiement' => null];
            }

            // LISTE D'ATTENTE
            if ($action === 'mettre_en_attente') {
                $pos = $this->nextQueuePosition($i->niveau_souhaite, $i->annee_scolaire);

                $i->update([
                    'statut'               => 'waiting',
                    'position_attente'     => $pos,
                    'remarques_admin'      => $remarques,
                    'traite_par_admin_id'  => $adminId,
                    'date_traitement'      => now(),
                ]);

                return ['inscription' => $i->fresh(), 'paiement' => null];
            }

            // ACCEPTER
            if ($action === 'accepter') {
                $i->update([
                    'statut'               => 'accepted',
                    'position_attente'     => null,
                    'classe_id'            => $classeId ?? $i->classe_id,
                    'remarques_admin'      => $remarques,
                    'traite_par_admin_id'  => $adminId,
                    'date_traitement'      => now(),
                ]);

                // Crée un paiement "virtuel" si un montant d'inscription est fourni
                if ($fraisInscription && $fraisInscription > 0) {
                    $paiement = Paiement::create([
                        'parent_id'        => $i->parent_id ?? null,             // parent pas encore créé
                        'inscription_id'   => $i->id,
                        'montant'          => $fraisInscription,
                        'type'             => 'inscription',
                        'methode_paiement' => $this->normalizePaymentMethod($methodePaiement),
                        'date_paiement'    => null,                              // important
                        'date_echeance'    => Carbon::now()->addDays($this->echeanceJours()),
                        'statut'           => 'en_attente',                      // en_attente | paye | expire | annule
                        'remarques'        => $remarques,
                    ]);
                }

                return ['inscription' => $i->fresh(), 'paiement' => $paiement];
            }

            // Par défaut: ne rien changer
            return ['inscription' => $i->fresh(), 'paiement' => null];
        });
    }

    /** Prochaine position de file d'attente pour un niveau/année. */
    private function nextQueuePosition(string $niveau, string $annee): int
    {
        $max = Inscription::where('statut', 'waiting')
            ->where('niveau_souhaite', $niveau)
            ->where('annee_scolaire', $annee)
            ->max('position_attente');

        return ((int) $max) + 1;
    }

    /**
     * Finalise après paiement:
     * - crée User (role parent si dispo), ParentModel, Enfant
     * - lie parent/enfant
     * - met à jour inscription & paiement
     * Retour: ['user'=>User,'parent'=>ParentModel,'enfant'=>Enfant]
     */
    private function finalizeAfterPayment(Inscription $i, Paiement $p): array
{
    return DB::transaction(function () use ($i, $p) {

        // 1) USER (login)
        $passwordPlain = Str::password(10);

        // On met à jour si l'email existe déjà
        $user = User::updateOrCreate(
            ['email' => $i->email_parent],
            [
                'name'     => trim($i->prenom_parent.' '.$i->nom_parent),
                'password' => Hash::make($passwordPlain),
                // Si ta table users a une colonne 'role', on force 'parent'
                // (sinon commente ces 2 lignes)
                'role'     => 'parent',
            ]
        );

        // Si tu utilises Spatie, on s'assure que le rôle "parent" est associé
        if (method_exists($user, 'assignRole')) {
            try { $user->syncRoles(['parent']); } catch (\Throwable $e) {}
        }

        // 2) PROFIL PARENT (on met à jour à chaque fois)
        $parent = ParentModel::updateOrCreate(
            ['user_id' => $user->id],
            [
                'telephone'                 => $i->telephone_parent,
                'adresse'                   => $i->adresse_parent,
                'profession'                => $i->profession_parent,
                'contact_urgence_nom'       => $i->contact_urgence_nom,
                'contact_urgence_telephone' => $i->contact_urgence_telephone,
            ]
        );

        // 3) ENFANT
        // Ton enum pour 'sexe' est ('garçon','fille'), donc on mappe M/F
        $sexe = $i->genre_enfant === 'M' ? 'garçon' : ($i->genre_enfant === 'F' ? 'fille' : null);

        // Normalisation champs TEXT (DB) depuis JSON (inscription)
        $allergies    = $i->allergies ? (is_array($i->allergies) ? implode(',', $i->allergies) : (string)$i->allergies) : null;
        $remarquesMed = $i->problemes_sante ? (is_array($i->problemes_sante) ? implode(', ', $i->problemes_sante) : (string)$i->problemes_sante) : null;

        $enfant = Enfant::updateOrCreate(
            [
                'nom'             => $i->nom_enfant,
                'prenom'          => $i->prenom_enfant,
                'date_naissance'  => $i->date_naissance_enfant,
            ],
            [
                'sexe'                 => $sexe,              // 'garçon' | 'fille' | null
                'classe_id'            => $i->classe_id,
                'allergies'            => $allergies,         // TEXT
                'remarques_medicales'  => $remarquesMed,      // TEXT
            ]
        );

        // Lier parent ↔ enfant (pivot)
        $parent->enfants()->syncWithoutDetaching([$enfant->id]);

        // 4) Marquer l’inscription comme confirmée et pointer le parent
        $i->update([
            'parent_id'       => $parent->id,
            'statut'          => 'accepted',
            'date_traitement' => now(),
        ]);

        // 5) Compléter le paiement
        $p->update([
            'parent_id'      => $parent->id,
            'date_paiement'  => now(),
            'statut'         => 'paye',
        ]);

        // (Optionnel) envoyer un email avec $passwordPlain

        return compact('user','parent','enfant');
    });
}

    /**
     * Simulation de paiement par ID (publique ou protégée selon ta route).
     * $action: 'paye' | 'expire' | 'annule'
     *
     * Retour:
     *  - si payé: ['expired'=>false, 'paiement'=>Paiement, 'inscription'=>Inscription, 'user'=>User, 'parent'=>ParentModel, 'enfant'=>Enfant]
     *  - sinon:   ['expired'=>bool,  'paiement'=>Paiement, 'inscription'=>Inscription]
     */
   // app/Services/InscriptionFlowService.php

public function simulatePayById(
    int $paiementId,
    string $action,
    ?string $methode = null,
    ?float $montant = null,
    ?string $reference = null,
    ?string $remarques = null
): array {
    return DB::transaction(function () use ($paiementId, $action, $methode, $montant, $reference, $remarques) {

        /** @var Paiement $paiement */
        $paiement   = Paiement::lockForUpdate()->findOrFail($paiementId);
        $inscription= $paiement->inscription()->lockForUpdate()->firstOrFail();

        // MAJ champs communs
        $paiement->update([
            'methode_paiement'      => $methode ?? $paiement->methode_paiement,
            'reference_transaction' => $reference ?? $paiement->reference_transaction,
            'remarques'             => $remarques ?? $paiement->remarques,
            // 'montant'            => $montant ?? $paiement->montant, // si tu veux
        ]);

        // 👉 Calcul d’expiration : on considère le jour entier de l’échéance
        $isExpired = false;
        if ($paiement->date_echeance) {
            $isExpired = Carbon::parse($paiement->date_echeance)
                ->endOfDay()
                ->lt(Carbon::now());
        }

        switch (strtolower($action)) {

            case 'paye':
            case 'payé':
            case 'paid':
                // ⛔️ Si expiré, on refuse le paiement et on rejette l’inscription
                if ($isExpired) {
                    $paiement->update([
                        'statut'        => 'expire',
                        'date_paiement' => null,
                    ]);
                    $inscription->update(['statut' => 'rejected']);

                    return [
                        'expired'     => true,
                        'paiement'    => $paiement->fresh(),
                        'inscription' => $inscription->fresh(),
                    ];
                }

                // ✅ Finalisation (création User/Parent/Enfant + marquer payé)
                $created = $this->finalizeAfterPayment($inscription, $paiement);

                return [
                    'expired'     => false,
                    'paiement'    => $paiement->fresh(),
                    'inscription' => $inscription->fresh(),
                    'user'        => $created['user'],
                    'parent'      => $created['parent'],
                    'enfant'      => $created['enfant'],
                ];

            case 'expire':
            case 'expired':
                $paiement->update([
                    'statut'        => 'expire',
                    'date_paiement' => null,
                ]);
                $inscription->update(['statut' => 'rejected']);
                return [
                    'expired'     => true,
                    'paiement'    => $paiement->fresh(),
                    'inscription' => $inscription->fresh(),
                ];

            case 'annule':
            case 'canceled':
            case 'cancelled':
                $paiement->update([
                    'statut'        => 'annule',
                    'date_paiement' => null,
                ]);
                // à toi de décider : ici on rejette aussi
                $inscription->update(['statut' => 'rejected']);
                return [
                    'expired'     => false,
                    'paiement'    => $paiement->fresh(),
                    'inscription' => $inscription->fresh(),
                ];

            default:
                return [
                    'expired'     => $isExpired,
                    'paiement'    => $paiement->fresh(),
                    'inscription' => $inscription->fresh(),
                ];
        }
    });
}

}
