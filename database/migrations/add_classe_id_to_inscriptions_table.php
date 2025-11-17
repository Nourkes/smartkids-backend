<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            // Vérifie si la colonne n'existe pas déjà pour éviter les doublons
            if (!Schema::hasColumn('inscriptions', 'classe_id')) {
                $table->foreignId('classe_id')
                      ->after('enfant_id') // position optionnelle
                      ->constrained('classe')
                      ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('inscriptions', 'classe_id')) {
                $table->dropForeign(['classe_id']);
                $table->dropColumn('classe_id');
            }
        });
    }
};