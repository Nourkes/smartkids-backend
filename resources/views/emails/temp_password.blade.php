{{-- resources/views/emails/temp_password.blade.php --}}
<p>Bonjour {{ $name }},</p>

<p>Votre compte <strong>SmartKids</strong> a été créé.</p>

<p>
  <strong>Identifiant (email) :</strong> {{ $email }}<br>
  <strong>Mot de passe provisoire :</strong> {{ $tempPassword }}
</p>

<p>
  À la première connexion, vous serez invité(e) à changer votre mot de passe.
  Ne partagez pas ces informations et supprimez cet e-mail après usage.
</p>

{{-- (optionnel) Un lien direct vers la page de connexion --}}
{{-- <p><a href="{{ config('app.front_login_url') }}">Se connecter</a></p> --}}
