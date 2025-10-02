<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('paiements', function (Blueprint $table) {
            // 🔹 Modifier les colonnes
            $table->enum('type', ['inscription', 'mensuel', 'activite', 'autre'])
                  ->default('autre')
                  ->change();

            $table->enum('methode_paiement', ['especes', 'cheque', 'virement', 'carte'])
                  ->default('especes')
                  ->change();

            $table->date('date_paiement')->nullable(false)->change();
            $table->date('date_echeance')->nullable()->change();

            $table->enum('statut', ['en_attente', 'valide', 'rejete', 'rembourse'])
                  ->default('en_attente')
                  ->change();
        });

        // 🔹 Supprimer les index s’ils existent (sans planter)
        $indexes = DB::select("SHOW INDEXES FROM paiements");

        $indexNames = collect($indexes)->pluck('Key_name')->toArray();

        foreach ([
            'paiements_parent_id_statut_index',
            'paiements_inscription_id_type_index',
            'paiements_participation_activite_id_index',
            'paiements_date_echeance_index',
            'paiements_statut_index'
        ] as $indexName) {
            if (in_array($indexName, $indexNames)) {
                DB::statement("ALTER TABLE paiements DROP INDEX $indexName");
            }
        }
    }

    public function down()
    {
        Schema::table('paiements', function (Blueprint $table) {
            // 🔹 Restaurer version 1
            $table->enum('type', ['inscription', 'mensuel', 'activite', 'materiel', 'sortie', 'autre'])
                  ->default('autre')
                  ->change();

            $table->enum('methode_paiement', ['especes', 'cheque', 'virement', 'carte', 'autre'])
                  ->default('especes')
                  ->change();

            $table->date('date_paiement')->nullable()->change();
            $table->date('date_echeance')->nullable(false)->change();

            $table->enum('statut', ['en_attente', 'paye', 'en_retard', 'annule'])
                  ->default('en_attente')
                  ->change();

            // 🔹 Ré-ajouter les index
            $table->index(['parent_id', 'statut']);
            $table->index(['inscription_id', 'type']);
            $table->index(['participation_activite_id']);
            $table->index(['date_echeance']);
            $table->index(['statut']);
        });
    }
};