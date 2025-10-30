<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_photo_to_educateurs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('educateurs', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('diplome'); // chemin storage
        });
    }
    public function down(): void {
        Schema::table('educateurs', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
