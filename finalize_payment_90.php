<?php

use App\Models\Paiement;
use App\Services\InscriptionFlowService;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$paymentId = 90;
$paiement = Paiement::find($paymentId);

if (!$paiement) {
    echo "Paiement ID $paymentId introuvable.\n";
    exit(1);
}

echo "Finalisation du paiement ID $paymentId (Parent: " . $paiement->inscription->email_parent . ")...\n";

$inscription = $paiement->inscription;
if (empty($inscription->adresse_parent)) {
    echo "⚠️ Adresse manquante. Ajout d'une adresse par défaut pour éviter l'erreur SQL...\n";
    $inscription->adresse_parent = 'Adresse non fournie';
    $inscription->save();
}

try {
    $service = app(InscriptionFlowService::class);
    $result = $service->simulatePayById(
        $paiement->id,
        'paye',
        'cash' // Force 'cash' methode
    );

    echo "✅ SUCCÈS !\n";
    echo "Statut Paiement: " . $result['paiement']->statut . "\n";
    echo "User ID: " . ($result['user']->id ?? 'N/A') . "\n";
    if (isset($result['user'])) {
        echo "Email envoyé à: " . $result['user']->email . "\n";
    }

} catch (\Throwable $e) {
    $msg = "❌ ERREUR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    file_put_contents('finalization_error.txt', $msg);
    echo $msg;
}
