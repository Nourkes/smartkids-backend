<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;

class NotificationService
{
    // Types de notifications constants
    const RAPPEL_PAIEMENT = 'rappel_paiement';
    const ACTIVITE_RECENTE = 'activite_recente';
    const ETAT_SANTE = 'etat_sante';
    const MENU_REPAS = 'menu_repas';
    const ABSENCE_PRESENCE = 'absence_presence';
    const EVALUATION_PROGRES = 'evaluation_progres';
    const ANNONCE_GENERALE = 'annonce_generale';
    const AFFECTATION_ELEVE = 'affectation_eleve';
    const PLANNING_ACTIVITE = 'planning_activite';
    const MESSAGE_RECU = 'message_recu';
    const TACHE_A_COMPLETER = 'tache_a_completer';
    const EVENEMENT_ETABLISSEMENT = 'evenement_etablissement';
    const DEMANDE_INSCRIPTION = 'demande_inscription';
    const RAPPORT_GENERE = 'rapport_genere';
    const ALERTE_SYSTEME = 'alerte_systeme';
    const MISE_A_JOUR_APP = 'mise_a_jour_app';
    const MODIFICATION_SECURITE = 'modification_securite';

    public function creerNotification(
        Model $destinataire,
        string $type,
        string $titre,
        string $message,
        array $data = [],
        string $priorite = 'normale',
        string $canal = 'app',
        Model $expediteur = null,
        $planifiePour = null
    ) {
        return Notification::create([
            'notifiable_type' => get_class($destinataire),
            'notifiable_id' => $destinataire->id,
            'sender_type' => $expediteur ? get_class($expediteur) : null,
            'sender_id' => $expediteur ? $expediteur->id : null,
            'type' => $type,
            'titre' => $titre,
            'message' => $message,
            'data' => $data,
            'priorite' => $priorite,
            'canal' => $canal,
            'planifie_pour' => $planifiePour
        ]);
    }

    // Notifications spécifiques pour les parents
    public function notifierRappelPaiement($parent, $montant, $dateEcheance, $inscription)
    {
        return $this->creerNotification(
            $parent,
            self::RAPPEL_PAIEMENT,
            'Rappel de paiement',
            "Un paiement de {$montant} DT est dû pour le {$dateEcheance}",
            [
                'montant' => $montant,
                'date_echeance' => $dateEcheance,
                'inscription_id' => $inscription->id
            ],
            'haute'
        );
    }

    public function notifierActiviteRecente($parent, $enfant, $activite, $photos = [])
    {
        return $this->creerNotification(
            $parent,
            self::ACTIVITE_RECENTE,
            'Nouvelle activité',
            "{$enfant->prenom} a participé à l'activité : {$activite->nom}",
            [
                'enfant_id' => $enfant->id,
                'activite_id' => $activite->id,
                'photos' => $photos
            ]
        );
    }

    public function notifierEtatSante($parent, $enfant, $description, $educateur)
    {
        return $this->creerNotification(
            $parent,
            self::ETAT_SANTE,
            'Information santé',
            "Concernant {$enfant->prenom} : {$description}",
            [
                'enfant_id' => $enfant->id,
                'description' => $description
            ],
            'urgente',
            'app',
            $educateur
        );
    }

    public function notifierAbsencePresence($parent, $enfant, $statut, $date)
    {
        $message = $statut === 'absent' ? 
            "{$enfant->prenom} était absent(e) le {$date}" :
            "{$enfant->prenom} était présent(e) le {$date}";

        return $this->creerNotification(
            $parent,
            self::ABSENCE_PRESENCE,
            'Information présence',
            $message,
            [
                'enfant_id' => $enfant->id,
                'statut' => $statut,
                'date' => $date
            ]
        );
    }

    public function notifierEvaluation($parent, $enfant, $matiere, $note, $educateur)
    {
        return $this->creerNotification(
            $parent,
            self::EVALUATION_PROGRES,
            'Nouvelle évaluation',
            "{$enfant->prenom} a obtenu {$note}/20 en {$matiere->nom}",
            [
                'enfant_id' => $enfant->id,
                'matiere_id' => $matiere->id,
                'note' => $note
            ],
            'normale',
            'app',
            $educateur
        );
    }

    // Notifications pour les éducateurs
    public function notifierNouvelEleve($educateur, $enfant, $classe)
    {
        return $this->creerNotification(
            $educateur,
            self::AFFECTATION_ELEVE,
            'Nouvel élève affecté',
            "{$enfant->prenom} {$enfant->nom} a été affecté à votre classe {$classe->nom}",
            [
                'enfant_id' => $enfant->id,
                'classe_id' => $classe->id
            ]
        );
    }

    public function notifierPlanningActivite($educateur, $activite)
    {
        return $this->creerNotification(
            $educateur,
            self::PLANNING_ACTIVITE,
            'Rappel activité',
            "Activité programmée : {$activite->nom} le {$activite->date_activite}",
            [
                'activite_id' => $activite->id
            ]
        );
    }

    // Notifications pour l'administrateur
    public function notifierDemandeInscription($admin, $listeAttente)
    {
        return $this->creerNotification(
            $admin,
            self::DEMANDE_INSCRIPTION,
            'Nouvelle demande d\'inscription',
            "Demande pour {$listeAttente->prenom_enfant} {$listeAttente->nom_enfant}",
            [
                'liste_attente_id' => $listeAttente->id
            ]
        );
    }

    public function notifierPaiementRecu($admin, $paiement)
    {
        return $this->creerNotification(
            $admin,
            'paiement_recu',
            'Paiement reçu',
            "Paiement de {$paiement->montant} DT reçu",
            [
                'paiement_id' => $paiement->id
            ]
        );
    }

    // Notifications transversales (pour tous)
    public function notifierTousLesUtilisateurs($titre, $message, $type = self::ANNONCE_GENERALE, $priorite = 'normale')
    {
        $notifications = [];
        
        // Notifier tous les parents
        $parents = \App\Models\ParentModel::all();
        foreach ($parents as $parent) {
            $notifications[] = $this->creerNotification($parent, $type, $titre, $message, [], $priorite);
        }
        
        // Notifier tous les éducateurs
        $educateurs = \App\Models\Educateur::all();
        foreach ($educateurs as $educateur) {
            $notifications[] = $this->creerNotification($educateur, $type, $titre, $message, [], $priorite);
        }
        
        // Notifier tous les admins
        $admins = \App\Models\Admin::all();
        foreach ($admins as $admin) {
            $notifications[] = $this->creerNotification($admin, $type, $titre, $message, [], $priorite);
        }
        
        return $notifications;
    }

    // Méthodes utilitaires
    public function envoyerNotificationsPlanifiees()
    {
        $notifications = Notification::planifiees()
            ->where('planifie_pour', '<=', now())
            ->whereNull('envoye_at')
            ->get();

        foreach ($notifications as $notification) {
            // Ici vous pouvez ajouter la logique d'envoi (email, SMS, push)
            $notification->marquerCommeEnvoyee();
        }

        return $notifications->count();
    }

    public function nettoyerAnciennesNotifications($joursAConserver = 30)
    {
        return Notification::where('created_at', '<', now()->subDays($joursAConserver))
            ->where('archive', true)
            ->delete();
    }
}