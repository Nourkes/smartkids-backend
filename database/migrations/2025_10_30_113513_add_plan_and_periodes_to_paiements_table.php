<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $t) {
            // plan: 'mensuel' | 'semestre' | 'annee' (nullable au début)
            if (!Schema::hasColumn('paiements', 'plan')) {
                $t->string('plan')->nullable()->after('type');
            }

            // périodes couvertes par ce paiement (ex: [0] pour le 1er mois)
            if (!Schema::hasColumn('paiements', 'periodes_couvertes')) {
                $t->json('periodes_couvertes')->nullable()->after('plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $t) {
            if (Schema::hasColumn('paiements', 'periodes_couvertes')) {
                $t->dropColumn('periodes_couvertes');
            }
            if (Schema::hasColumn('paiements', 'plan')) {
                $t->dropColumn('plan');
            }
        });
    }
};
