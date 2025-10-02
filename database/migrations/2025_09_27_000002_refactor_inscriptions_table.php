<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Colonnes si besoin
        Schema::table('inscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('inscriptions', 'niveau_souhaite')) {
                $table->string('niveau_souhaite', 20)->nullable()->after('id');
            }
            if (!Schema::hasColumn('inscriptions', 'statut')) {
                $table->string('statut', 20)->default('pending')->after('niveau_souhaite');
            }
        });

        // Index (ne pas recréer s’ils existent déjà)
        if (!$this->indexExists('inscriptions', 'inscriptions_niveau_souhaite_statut_index')) {
            Schema::table('inscriptions', function (Blueprint $table) {
                $table->index(['niveau_souhaite','statut'], 'inscriptions_niveau_souhaite_statut_index');
            });
        }

        if (!$this->indexExists('inscriptions', 'inscriptions_classe_id_statut_index')) {
            Schema::table('inscriptions', function (Blueprint $table) {
                $table->index(['classe_id','statut'], 'inscriptions_classe_id_statut_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('inscriptions', 'inscriptions_niveau_souhaite_statut_index')) {
            Schema::table('inscriptions', function (Blueprint $table) {
                $table->dropIndex('inscriptions_niveau_souhaite_statut_index');
            });
        }
        if ($this->indexExists('inscriptions', 'inscriptions_classe_id_statut_index')) {
            Schema::table('inscriptions', function (Blueprint $table) {
                $table->dropIndex('inscriptions_classe_id_statut_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $rows = DB::select("
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
            LIMIT 1
        ", [$db, $table, $index]);

        return !empty($rows);
    }
};
