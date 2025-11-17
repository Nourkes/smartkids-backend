@php
    // Sécurités au cas où
    $deeplink  = $deeplink  ?? null;
    $fallback  = $webFallback ?? (isset($paiement) && $paiement?->public_token
                    ? ('http://10.0.2.2:8000/pay/'.$paiement->public_token)
                    : null);
@endphp

<p>Votre demande d’inscription a été acceptée 🎉</p>

@if($deeplink)
<p>
  <a href="{{ $deeplink }}" style="display:inline-block;padding:10px 16px;background:#4F46E5;color:#fff;border-radius:8px;text-decoration:none">
    Payer dans l’app SmartKids
  </a>
</p>
@endif

@if($fallback)
<p>Si le bouton n’ouvre rien, cliquez ici :
  <a href="{{ $fallback }}">{{ $fallback }}</a>
</p>
@endif
