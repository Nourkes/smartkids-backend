# Cas d'Utilisation : Ajouter un Menu de Cantine

Ce document décrit le processus permettant à un administrateur d'ajouter un nouveau menu pour la cantine scolaire.

## 👥 Acteurs
1.  **Admin** : Responsable de la saisie des menus.
2.  **Système** : Backend vérifiant les données et stockant le menu.

## 📋 Pré-conditions
*   L'Administrateur est authentifié et possède les droits de gestion "Infrastructure/Cantine".

## 🛤️ Scénario Principal

1.  **Accès au formulaire**
    *   L'**Admin** navigue vers la section "Gestion de la Cantine".
    *   L'**Admin** clique sur le bouton "Ajouter un Menu".
    *   Le **Système** affiche le formulaire de création.

2.  **Saisie des informations**
    *   L'**Admin** renseigne la **Date** du menu (`date_menu`).
    *   L'**Admin** sélectionne le **Type de repas** (ex: Déjeuner, Goûter) (`type_repas`).
    *   L'**Admin** saisit la **Description** du repas (composition du menu) (`description`).
    *   L'**Admin** saisit les **Ingrédients** ou allergènes éventuels (`ingredients`) [Optionnel].

3.  **Validation et Enregistrement**
    *   L'**Admin** soumet le formulaire.
    *   Le **Système** vérifie qu'aucun menu n'existe déjà pour ce *couple Date + Type*.
    *   Le **Système** enregistre le nouveau menu.
    *   Le **Système** affiche une confirmation de création.

## ⚠️ Scénarios d'Exception

*   **Doublon détecté** : Si un menu existe déjà pour la même date et le même type de repas, le système refuse l'enregistrement et demande si l'utilisateur souhaite modifier l'existant.
*   **Champs obligatoires manquants** : La date, le type et la description sont requis. Le système bloque la soumission tant qu'ils ne sont pas remplis.

## 📝 Modèle de Données (Référence)
Basé sur la structure actuelle (`Menu` model) :
*   `date_menu` (Date)
*   `type_repas` (String)
*   `description` (String/Text) : Contenu principal du menu.
*   `ingredients` (String/Text) : Liste des ingrédients/allergènes.
