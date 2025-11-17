<?php
return [
    'days'       => [1,2,3,4,5,6],      // 1=lundi … 6=samedi
    'day_start'  => '08:00',
    'day_end'    => '16:00',
    'block_min'  => 60,
    'breaks'     => [
        ['start' => '12:00', 'end' => '13:00'],
    ],
    'educateur_max_h_week' => 20, // heures/semaine

    // Comportement : on préfère 1 prof par matière, on ne bascule qu'en cas de dépassement/indispo
    'prefer_single_teacher_per_subject' => true,
        // NEW: taille de grappe conseillée (ex: 2 blocs = 2h si block_min=60)
    'prefer_consecutive_blocks' => 2,

    // NEW: on n’essaie jamais plus que ça d’affilée (sécurité)
    'max_consecutive_blocks'    => 3,
];
