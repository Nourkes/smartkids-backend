@component('mail::message')
# Bonjour {{ $i->prenom_parent }} {{ $i->nom_parent }},

Nous avons bien reçu votre **demande d'inscription** pour
**{{ $i->prenom_enfant }} {{ $i->nom_enfant }}** ({{ $i->niveau_souhaite }}) – **{{ $i->annee_scolaire }}**.

Vous serez contacté(e) par email après traitement par l’administration.

Merci,
L’équipe SmartKids
@endcomponent
