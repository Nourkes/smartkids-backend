<?php
// ================================================================================
// 1. MIGRATION MISE À JOUR POUR LA TABLE INSCRIPTIONS
// database/migrations/2024_01_22_000001_update_inscriptions_table_remove_payment_fields.php
// ================================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            // Supprimer les champs de paiement qui seront gérés par la table paiements
            if (Schema::hasColumn('inscriptions', 'frais_inscription')) {
                $table->dropColumn('frais_inscription');
            }
            if (Schema::hasColumn('inscriptions', 'frais_mensuel')) {
                $table->dropColumn('frais_mensuel');
            }
        });
    }

    public function down()
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->decimal('frais_inscription', 10, 2)->nullable();
            $table->decimal('frais_mensuel', 10, 2)->nullable();
        });
    }
};