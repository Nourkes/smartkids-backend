<?php
// database/migrations/2024_01_15_000001_update_inscriptions_table_structure.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            // Supprimer la référence à liste_attente si elle existe
            if (Schema::hasColumn('inscriptions', 'liste_attente_id')) {
                $table->dropForeign(['liste_attente_id']);
                $table->dropColumn('liste_attente_id');
            }
            
            // Ajouter les nouveaux champs
            $table->integer('position_attente')->nullable()->after('statut');
            $table->timestamp('date_traitement')->nullable()->after('position_attente');
            $table->unsignedBigInteger('traite_par_admin_id')->nullable()->after('date_traitement');
            
            // Index pour optimiser les requêtes
            $table->index(['classe_id', 'statut'], 'idx_classe_statut');
            $table->index(['statut', 'position_attente'], 'idx_statut_position');
            $table->index(['annee_scolaire'], 'idx_annee_scolaire');
            
            // Foreign key pour l'admin
            $table->foreign('traite_par_admin_id')->references('id')->on('users')->onDelete('set null');
        });

        // Mettre à jour les valeurs de statut existantes si nécessaire
        DB::statement("ALTER TABLE inscriptions MODIFY COLUMN statut ENUM('en_attente', 'accepte', 'refuse', 'liste_attente') DEFAULT 'en_attente'");
    }

    public function down()
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropForeign(['traite_par_admin_id']);
            $table->dropIndex('idx_classe_statut');
            $table->dropIndex('idx_statut_position');
            $table->dropIndex('idx_annee_scolaire');
            $table->dropColumn(['position_attente', 'date_traitement', 'traite_par_admin_id']);
        });
        
        // Remettre l'ancien enum si nécessaire
        DB::statement("ALTER TABLE inscriptions MODIFY COLUMN statut VARCHAR(50) DEFAULT 'en_attente'");
    }
};