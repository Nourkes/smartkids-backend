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
use Illuminate\Support\Facades\Mail;
use App\Mail\InscriptionReceivedMail;
use App\Mail\InscriptionAcceptedMail;

class InscriptionFlowService
{
    private const PAIEMENT_METHODS = ['cash', 'carte', 'en_ligne'];

    private function echeanceJours(): int
    {
        return (int) config('school.payment_deadline_days', 3);
    }

    private function normalizePaymentMethod(?string $m): string
    {
        $m = strtolower((string) $m);
        return in_array($m, self::PAIEMENT_METHODS, true) ? $m : 'cash';
    }

    public function create(array $data): Inscription
    {
        return DB::transaction(function () use ($data) {

            $form = InscriptionForm::create(['payload' => $data]);

            $inscription = Inscription::create([
                'form_id' => $form->id,
                'niveau_souhaite' => $data['niveau_souhaite'],
                'annee_scolaire' => $data['annee_scolaire'],
                'date_inscription' => now(),
                'statut' => 'pending',
                'nom_parent' => $data['nom_parent'],
                'prenom_parent' => $data['prenom_parent'],
                'email_parent' => $data['email_parent'],
                'telephone_parent' => $data['telephone_parent'],
                'adresse_parent' => $data['adresse_parent'] ?? null,
                'profession_parent' => $data['profession_parent'] ?? null,
                'nom_enfant' => $data['nom_enfant'],
                'prenom_enfant' => $data['prenom_enfant'],
                'date_naissance_enfant' => $data['date_naissance_enfant'],
                'genre_enfant' => $data['genre_enfant'] ?? null,
                'problemes_sante' => $data['problemes_sante'] ?? null,
                'allergies' => $data['allergies'] ?? null,
                'medicaments' => $data['medicaments'] ?? null,
                'documents_fournis' => $data['documents_fournis'] ?? null,
                'contact_urgence_nom' => $data['contact_urgence_nom'] ?? null,
                'contact_urgence_telephone' => $data['contact_urgence_telephone'] ?? null,
                'remarques' => $data['remarques'] ?? null,
            ]);

            try {
                Mail::to($inscription->email_parent)
                    ->send(new InscriptionReceivedMail($inscription));
            } catch (\Throwable $e) {
                \Log::warning('Email accusé inscription KO', [
                    'id' => $inscription->id,
                    'err' => $e->getMessage()
                ]);
            }

            return $inscription;
        });
    }

    public function accept(
        Inscription $i,
        ?int $classeId,
        int $adminId,
        ?string $remarques = null
    ): array {
        return $this->decide($i, 'accepter', $classeId, $adminId, $remarques);
    }

    public function wait(Inscription $i, int $adminId, ?string $remarques = null): array
    {
        return $this->decide($i, 'mettre_en_attente', null, $adminId, $remarques);
    }

    public function reject(Inscription $i, int $adminId, ?string $remarques = null): array
    {
        return $this->decide($i, 'refuser', null, $adminId, $remarques);
    }

    /**
     * Décision admin
     * 🔥 Si accepté : crée paiement 1er mois SANS montant fixe (calculé au paiement)
     */
    public function decide(
        Inscription $i,
        string $action,
        ?int $classeId,
        int $adminId,
        ?string $remarques = null
    ): array {
        $paiement = null;

        return DB::transaction(function () use (&$paiement, $i, $action, $classeId, $adminId, $remarques) {

            // REFUSER
            if ($action === 'refuser') {
                $i->update([
                    'statut' => 'rejected',
                    'position_attente' => null,
                    'classe_id' => null,
                    'remarques_admin' => $remarques,
                    'traite_par_admin_id' => $adminId,
                    'date_traitement' => now(),
                ]);

                return ['inscription' => $i->fresh(), 'paiement' => null];
            }

            // LISTE D'ATTENTE
            if ($action === 'mettre_en_attente') {
                $pos = $this->nextQueuePosition($i->niveau_souhaite, $i->annee_scolaire);

                $i->update([
                    'statut' => 'waiting',
                    'position_attente' => $pos,
                    'remarques_admin' => $remarques,
                    'traite_par_admin_id' => $adminId,
                    'date_traitement' => now(),
                ]);

                return ['inscription' => $i->fresh(), 'paiement' => null];
            }

            // ACCEPTER
            if ($action === 'accepter') {
                $i->update([
                    'statut' => 'accepted',
                    'position_attente' => null,
                    'classe_id' => $classeId ?? $i->classe_id,
                    'remarques_admin' => $remarques,
                    'traite_par_admin_id' => $adminId,
                    'date_traitement' => now(),
                ]);

                // 🔥 Créer paiement 1er mois SANS montant (calculé dynamiquement)
                $dateEcheance = Carbon::now()->addDays($this->echeanceJours());

                $paiement = Paiement::create([
                    'parent_id' => null,
                    'inscription_id' => $i->id,
                    'montant' => 0, // 🔥 Sera calculé au moment du paiement
                    'type' => 'inscription',
                    'plan' => 'mensuel',
                    'periodes_couvertes' => null, // 🔥 Défini au paiement
                    'methode_paiement' => 'en_ligne',
                    'date_paiement' => null,
                    'date_echeance' => $dateEcheance,
                    'statut' => 'en_attente',
                    'remarques' => $remarques,
                ]);

                // Token public avec deadline
                $paiement->forceFill([
                    'public_token' => Str::random(96),
                    'public_token_expires_at' => $dateEcheance,
                    'consumed_at' => null,
                ])->save();

                // Liens
// Liens (BACK UNIQUEMENT)
                $apiBase = rtrim(config('smartkids.api_base', 'http://10.0.2.2:8000/api'), '/');

                // URLs publiques pour l’écran de paiement
                $quoteUrl = $apiBase . '/public/payments/' . $paiement->public_token . '/quote';
                $confirmUrl = $apiBase . '/public/payments/' . $paiement->public_token . '/confirm';

                // Deep-link AU FORMAT ATTENDU (query params 'quote' et 'confirm')
                $deepBase = rtrim(config('smartkids.deep_link_base', 'smartkids://pay'), '/');
                $deeplink = $deepBase . '?' . http_build_query([
                    'quote' => $quoteUrl,
                    'confirm' => $confirmUrl,
                ], '', '&', PHP_QUERY_RFC3986);

                // Fallback Web (facultatif)
                $webFallback = rtrim(config('smartkids.web_fallback_base', 'http://10.0.2.2:8000/pay'), '/')
                    . '/' . $paiement->public_token;


                // Email avec lien + deadline
                try {
                    Mail::to($i->email_parent)->send(
                        new \App\Mail\FirstMonthPaymentMail($i, $paiement, $deeplink, $webFallback)
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Email paiement 1er mois KO', [
                        'inscription_id' => $i->id,
                        'err' => $e->getMessage()
                    ]);
                }

                return ['inscription' => $i->fresh(), 'paiement' => $paiement->fresh()];
            }

            return ['inscription' => $i->fresh(), 'paiement' => null];
        });
    }

    private function nextQueuePosition(string $niveau, string $annee): int
    {
        $max = Inscription::where('statut', 'waiting')
            ->where('niveau_souhaite', $niveau)
            ->where('annee_scolaire', $annee)
            ->max('position_attente');

        return ((int) $max) + 1;
    }

    /**
     * Finalise après paiement 1er mois
     * 🔥 Calcule le montant au moment du paiement (pas avant)
     */
    public function finalizeAfterFirstMonthPayment(Inscription $i, Paiement $p): array
    {
        try {
            return DB::transaction(function () use ($i, $p) {

                // 🔥 Calculer le montant
                $paymentService = app(\App\Services\PaymentService::class);
                $quote = $paymentService->quoteFirstMonthProrata($i, now());

                $p->update([
                    'montant' => $quote['montant_du'],
                    'periodes_couvertes' => [$quote['periode_index']],
                ]);

                // 1) Normaliser l'email
                $emailNormalized = strtolower(trim($i->email_parent));

                if (!filter_var($emailNormalized, FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception("Email invalide : {$i->email_parent}");
                }

                // 2) Créer/Récupérer User
                \Log::info('🔵 Vérification User', ['email' => $emailNormalized]);

                $user = User::firstOrNew(['email' => $emailNormalized]);
                $isNewUser = !$user->exists;

                $passwordPlain = null;

                if ($isNewUser) {
                    // NOUVEAU parent
                    $passwordPlain = Str::password(10);
                    $user->fill([
                        'name' => trim($i->prenom_parent . ' ' . $i->nom_parent),
                        'password' => Hash::make($passwordPlain),
                        'role' => 'parent',
                        'must_change_password' => true,
                    ]);
                    $user->save();
                    \Log::info('✅ Nouveau User créé', ['user_id' => $user->id]);
                } else {
                    // PARENT EXISTANT - Ne PAS toucher au password
                    $user->name = trim($i->prenom_parent . ' ' . $i->nom_parent);
                    $user->save();
                    \Log::info('✅ User existant récupéré', ['user_id' => $user->id]);
                }

                if (!$user || !$user->id) {
                    throw new \Exception("❌ User non créé pour : {$emailNormalized}");
                }

                \Log::info('✅ User créé', [
                    'user_id' => $user->id,
                    'wasRecentlyCreated' => $user->wasRecentlyCreated
                ]);

                // 3) Assigner rôle Spatie
                try {
                    $user->syncRoles(['parent']);
                    \Log::info('✅ Rôle assigné', ['user_id' => $user->id]);
                } catch (\Throwable $e) {
                    \Log::warning('⚠️ Rôle non assigné', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // 4) Créer Parent
                \Log::info('🔵 Création Parent', ['user_id' => $user->id]);

                $parent = ParentModel::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'telephone' => $i->telephone_parent,
                        'adresse' => $i->adresse_parent,
                        'profession' => $i->profession_parent,
                        'contact_urgence_nom' => $i->contact_urgence_nom,
                        'contact_urgence_telephone' => $i->contact_urgence_telephone,
                    ]
                );

                \Log::info('✅ Parent créé', ['parent_id' => $parent->id]);

                // 5) Créer Enfant
                $sexe = $i->genre_enfant === 'M' ? 'garçon' : ($i->genre_enfant === 'F' ? 'fille' : null);
                $allergies = $i->allergies ? (is_array($i->allergies) ? implode(',', $i->allergies) : (string) $i->allergies) : null;
                $remarquesMed = $i->problemes_sante ? (is_array($i->problemes_sante) ? implode(', ', $i->problemes_sante) : (string) $i->problemes_sante) : null;

                \Log::info('🔵 Création Enfant', [
                    'nom' => $i->nom_enfant,
                    'prenom' => $i->prenom_enfant
                ]);

                $enfant = Enfant::updateOrCreate(
                    [
                        'nom' => $i->nom_enfant,
                        'prenom' => $i->prenom_enfant,
                        'date_naissance' => $i->date_naissance_enfant,
                    ],
                    [
                        'sexe' => $sexe,
                        'classe_id' => $i->classe_id,
                        'allergies' => $allergies,
                        'remarques_medicales' => $remarquesMed,
                    ]
                );

                $isNewChild = $enfant->wasRecentlyCreated;

                \Log::info('✅ Enfant créé/récupéré', [
                    'enfant_id' => $enfant->id,
                    'isNewChild' => $isNewChild
                ]);

                // 6) Lier enfant au parent
                $parent->enfants()->syncWithoutDetaching([$enfant->id]);

                // 7) Mettre à jour inscription
                $i->update([
                    'parent_id' => $parent->id,
                    'statut' => 'accepted',
                    'date_traitement' => now(),
                ]);

                // 8) Mettre à jour paiement
                $p->update([
                    'parent_id' => $parent->id,
                    'date_paiement' => now(),
                    'statut' => 'paye',
                ]);

                \Log::info('✅ Transaction complète', [
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'enfant_id' => $enfant->id
                ]);

                // 9) Envoyer email selon le cas
                try {
                    $classe = \App\Models\Classe::find($i->classe_id);
                    $className = $classe ? $classe->nom : 'Non définie';

                    if ($isNewUser && $isNewChild) {
                        // CAS 1: Parent NOUVEAU + Enfant NOUVEAU
                        Mail::to($user->email)->send(
                            new \App\Mail\NewParentAccountMail(
                                $parent->prenom . ' ' . $parent->nom,
                                $enfant->prenom . ' ' . $enfant->nom,
                                $user->email,
                                $passwordPlain,
                                $className,
                                $i->annee_scolaire
                            )
                        );
                        \Log::info('✅ Email "Nouveau compte" envoyé', ['email' => $user->email]);

                    } elseif (!$isNewUser && $isNewChild) {
                        // CAS 2: Parent EXISTANT + Enfant NOUVEAU
                        Mail::to($user->email)->send(
                            new \App\Mail\NewChildAddedMail(
                                $parent->prenom . ' ' . $parent->nom,
                                $enfant->prenom . ' ' . $enfant->nom,
                                $className,
                                $i->annee_scolaire
                            )
                        );
                        \Log::info('✅ Email "Nouvel enfant" envoyé', ['email' => $user->email]);

                    } else {
                        // CAS 3: Parent EXISTANT + Enfant EXISTANT (réinscription)
                        Mail::to($user->email)->send(
                            new \App\Mail\ChildReenrolledMail(
                                $parent->prenom . ' ' . $parent->nom,
                                $enfant->prenom . ' ' . $enfant->nom,
                                $className,
                                $i->annee_scolaire
                            )
                        );
                        \Log::info('✅ Email "Réinscription" envoyé', ['email' => $user->email]);
                    }
                } catch (\Throwable $e) {
                    \Log::error('❌ Email non envoyé', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }

                return compact('user', 'parent', 'enfant', 'quote');
            });

        } catch (\Throwable $e) {
            \Log::error('❌ ERREUR TRANSACTION', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    /**
     * Simulation de paiement
     */
    public function simulatePayById(
        int $paiementId,
        string $action,
        ?string $methode = null,
        ?float $montant = null,
        ?string $reference = null,
        ?string $remarques = null
    ): array {
        return DB::transaction(function () use ($paiementId, $action, $methode, $montant, $reference, $remarques) {

            $paiement = Paiement::lockForUpdate()->findOrFail($paiementId);
            $inscription = $paiement->inscription()->lockForUpdate()->firstOrFail();

            $paiement->update([
                'methode_paiement' => $methode ?? $paiement->methode_paiement,
                'reference_transaction' => $reference ?? $paiement->reference_transaction,
                'remarques' => $remarques ?? $paiement->remarques,
            ]);

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
                    if ($isExpired) {
                        $paiement->update([
                            'statut' => 'expire',
                            'date_paiement' => null,
                        ]);
                        $inscription->update(['statut' => 'rejected']);
                        app(\App\Services\PaymentHousekeepingService::class)->expireAndCleanup($paiement);

                        return [
                            'expired' => true,
                            'paiement' => $paiement->fresh(),
                            'inscription' => $inscription->fresh(),
                        ];
                    }

                    // ✅ Finalisation avec calcul dynamique du montant
                    $created = $this->finalizeAfterFirstMonthPayment($inscription, $paiement);

                    return [
                        'expired' => false,
                        'paiement' => $paiement->fresh(),
                        'inscription' => $inscription->fresh(),
                        'user' => $created['user'],
                        'parent' => $created['parent'],
                        'enfant' => $created['enfant'],
                        'quote' => $created['quote'], // 🔥 Info sur le montant calculé
                    ];

                case 'expire':
                case 'expired':
                    $paiement->update([
                        'statut' => 'expire',
                        'date_paiement' => null,
                    ]);
                    app(\App\Services\PaymentHousekeepingService::class)->expireAndCleanup($paiement);
                    $inscription->update(['statut' => 'rejected']);

                    return [
                        'expired' => true,
                        'paiement' => $paiement->fresh(),
                        'inscription' => $inscription->fresh(),
                    ];

                case 'annule':
                case 'canceled':
                case 'cancelled':
                    $paiement->update([
                        'statut' => 'annule',
                        'date_paiement' => null,
                    ]);
                    $inscription->update(['statut' => 'rejected']);

                    return [
                        'expired' => false,
                        'paiement' => $paiement->fresh(),
                        'inscription' => $inscription->fresh(),
                    ];

                default:
                    return [
                        'expired' => $isExpired,
                        'paiement' => $paiement->fresh(),
                        'inscription' => $inscription->fresh(),
                    ];
            }
        });
    }
}