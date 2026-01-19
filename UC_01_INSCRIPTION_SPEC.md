# Cas d'Utilisation : Traiter une demande d'inscription

Ce document décrit le flux de traitement d'une demande d'inscription par l'administration.

## 👥 Acteurs
1.  **Admin** : Acteur principal, responsable de la validation des inscriptions.
2.  **Système** : Backend qui exécute les mises à jour et notifications.
3.  **Parent** : Acteur secondaire (destinataire des notifications).

## 📋 Pré-conditions
*   L'Administrateur est authentifié.
*   Il existe au moins une demande d'inscription avec le statut `pending` (soumise par un parent via le portail public).

## 🛤️ Scénario Principal (Succès : Acceptation)

1.  **Consultation des demandes**
    *   L'**Admin** accède à la liste des inscriptions en attente.
    *   Le **Système** affiche la liste des nouvelles demandes (triées par date).

2.  **Analyse du dossier**
    *   L'**Admin** sélectionne une demande pour voir les détails.
    *   L'**Admin** vérifie la conformité des informations (documents, âge, places disponibles dans le niveau).

3.  **Validation**
    *   L'**Admin** valide la demande et assigne une classe (optionnel à ce stade).
    *   Le **Système** :
        *   Change le statut de l'inscription à `accepted`.
        *   Crée l'enregistrement de **Paiement** en attente.
        *   Génère les liens de paiement sécurisés.
    *   Le **Système** notifie le **Parent** par email avec la confirmation et le lien de paiement.
    *   **Fin du cas d'utilisation** (Le dossier passe en attente de paiement).

## 🔀 Scénarios Alternatifs

### A. Refus de l'inscription
Au lieu d'accepter l'étape 3 :
1.  L'**Admin** décide de **REFUSER** la demande (motif : dossier incomplet, hors critères, etc.).
2.  Le **Système** :
    *   Met à jour le statut à `rejected`.
    *   Enregistre les remarques de l'admin.
3.  Le **Système** envoie un email de refus au **Parent** (avec motif et contact).
4.  **Fin du processus** (Pas de paiement ni de compte créé).

### B. Mise sur Liste d'Attente
Au lieu d'accepter à l'étape 3 :
1.  L'**Admin** décide de mettre la demande sur **LISTE D'ATTENTE** (plus de places).
2.  Le **Système** :
    *   Calcule la prochaine position disponible dans la file d'attente pour ce niveau.
    *   Met à jour le statut à `waiting`.
    *   Enregistre la position et les remarques.
3.  Le **Système** envoie un email au **Parent** (indiquant sa position N sur la liste).
4.  **Processus en pause** (En attente d'une libération de place).

## ⚠️ Scénarios d'Exception
*   **Données invalides à la soumission** : L'API rejette la demande (Erreur 400/422). Le formulaire réaffiche les erreurs au Parent. Aucune inscription créée.
*   **Service Email indisponible** : L'inscription est créée en base, mais l'envoi de mail échoue (log d'erreur). Le système devrait avoir un mécanisme de retry pour les notifications.
*   **Expiration du Paiement** : Si le Parent ne paie pas avant la date limite, un autre processus (batch) passera le paiement/inscription en statut "Expiré" ou "Annulé".
