<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pay/{token}', function (string $token) {
    $quote   = url("/api/public/payments/{$token}/quote");
    $confirm = url("/api/public/payments/{$token}/confirm");

    // Construire les URLs sans double encodage
    $scheme = "smartkids://pay?quote=" . urlencode($quote) . "&confirm=" . urlencode($confirm);
    $intent = "intent://pay?quote=" . urlencode($quote) . "&confirm=" . urlencode($confirm) 
            . "#Intent;scheme=smartkids;package=com.example.smartkids_clean;end";

    return view('pay.fallback', compact('scheme', 'intent', 'quote', 'confirm'));
})->where('token', '.*')->name('pay.fallback');