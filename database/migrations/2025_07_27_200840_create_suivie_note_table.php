<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('suivie_note', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enfant_id')->constrained('enfant')->onDelete('cascade');
            $table->foreignId('matiere_id')->constrained('matiere')->onDelete('cascade');
            $table->decimal('note', 4, 2); // Note sur 20 (ex: 15.75)
            $table->enum('type_evaluation', ['controle', 'devoir', 'oral', 'pratique', 'projet']);
            $table->date('date_evaluation');
            $table->string('trimestre'); // Ex: "T1", "T2", "T3"
            $table->string('annee_scolaire'); // Ex: "2024-2025"
            $table->text('commentaire')->nullable();
            $table->foreignId('educateur_id')->constrained('educateurs')->onDelete('cascade'); // Qui a donné la note
            $table->timestamps();
            
            // Index pour optimiser les requêtes fréquentes
            $table->index(['enfant_id', 'matiere_id', 'trimestre']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('suivie_note');
    }
};