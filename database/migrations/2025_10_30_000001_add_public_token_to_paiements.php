<?php
// database/migrations/2025_10_30_000001_add_public_token_to_paiements.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $t) {
            if (!Schema::hasColumn('paiements','public_token')) {
                $t->string('public_token', 128)->nullable()->unique()->after('statut');
            }
            if (!Schema::hasColumn('paiements','public_token_expires_at')) {
                $t->timestamp('public_token_expires_at')->nullable()->after('public_token');
            }
            if (!Schema::hasColumn('paiements','consumed_at')) {
                $t->timestamp('consumed_at')->nullable()->after('public_token_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $t) {
            if (Schema::hasColumn('paiements','consumed_at')) $t->dropColumn('consumed_at');
            if (Schema::hasColumn('paiements','public_token_expires_at')) $t->dropColumn('public_token_expires_at');
            if (Schema::hasColumn('paiements','public_token')) $t->dropColumn('public_token');
        });
    }
};
