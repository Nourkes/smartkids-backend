<?php
return [
    'academic_years' => [
        '2025-2026' => '2025-09-01',
    ],
    'mois_par_semestre' => ['S1' => 3, 'S2' => 5,'s3'=>3],
    'first_month_prorata' => ['enabled' => true], 
    'grace_days' => 0,
    'deep_link_base' => env('SMARTKIDS_DEEP_LINK_BASE', 'https://pay.smartkids.tn/p/'),

    // Validité du lien (en heures)
    'payment_link_ttl_hours' => env('SMARTKIDS_PAYMENT_LINK_TTL_HOURS', 72),

    // # de mois d’école (déjà utilisé par ton PaymentService)
    'mois_par_annee' => env('SMARTKIDS_MOIS_PAR_ANNEE', 9),
    'frais_mensuel' => (float) env('SMARTKIDS_FRAIS_MENSUEL', 300),
    'web_fallback_base' => env('SMARTKIDS_WEB_FALLBACK_BASE', 'http://10.0.2.2:8000/pay'),



];
