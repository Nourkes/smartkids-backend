<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->onDelete('cascade');
            $table->foreignId('inscription_id')->nullable()->constrained('inscriptions')->onDelete('cascade');
            $table->foreignId('participation_activite_id')->nullable()->constrained('participation_activite')->onDelete('cascade');
            $table->decimal('montant', 10, 2);
            $table->enum('type', ['inscription', 'mensuel', 'activite', 'autre']);
            $table->enum('methode_paiement', ['especes', 'cheque', 'virement', 'carte']);
            $table->date('date_paiement');
            $table->date('date_echeance')->nullable();
            $table->enum('statut', ['en_attente', 'valide', 'rejete', 'rembourse'])->default('en_attente');
            $table->string('reference_transaction')->nullable();
            $table->text('remarques')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('paiements');
    }
};