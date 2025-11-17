<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enfant_id')->constrained('enfant')->onDelete('cascade');
            $table->foreignId('liste_attente_id')->nullable()->constrained('liste_attente')->onDelete('set null');
            $table->string('annee_scolaire'); // Ex: "2024-2025"
            $table->date('date_inscription');
            $table->enum('statut', ['en_cours', 'validee', 'suspendue', 'terminee'])->default('en_cours');
            $table->decimal('frais_inscription', 10, 2);
            $table->decimal('frais_mensuel', 10, 2);
            $table->text('documents_fournis')->nullable(); // JSON des documents
            $table->text('remarques')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('inscriptions');
    }
};