<?php
// database/migrations/XXXX_XX_XX_XXXXXX_add_telephone_to_educateurs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('educateurs', function (Blueprint $table) {
            // string(20) suffit généralement ; nullable pour ne pas casser les anciens enregistrements
            $table->string('telephone', 20)->nullable()->after('user_id');
            // Si tu veux l’unicité (optionnel) :
            // $table->unique('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('educateurs', function (Blueprint $table) {
            // Si tu as mis un index unique :
            // $table->dropUnique(['telephone']);
            $table->dropColumn('telephone');
        });
    }
};
