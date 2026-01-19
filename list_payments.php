<?php

use App\Models\Paiement;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$paiements = Paiement::where('statut', 'en_attente')
    ->with('inscription')
    ->latest()
    ->take(5)
    ->get();

foreach ($paiements as $p) {
    echo "ID: {$p->id} | Email: " . ($p->inscription->email_parent ?? 'Aucun') . " | Montant: {$p->montant} | Date: {$p->created_at}\n";
}
