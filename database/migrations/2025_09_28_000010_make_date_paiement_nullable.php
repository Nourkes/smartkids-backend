<?php
// database/migrations/2025_09_28_000010_make_date_paiement_nullable.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Pas besoin de doctrine/dbal : on utilise du SQL brut
        DB::statement("ALTER TABLE paiements MODIFY date_paiement DATE NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE paiements MODIFY date_paiement DATE NOT NULL");
    }
};

