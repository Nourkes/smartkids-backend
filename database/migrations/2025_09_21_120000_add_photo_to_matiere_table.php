<?php
// database/migrations/2025_09_21_120000_add_photo_to_matiere_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('matiere', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('nom'); // URL ou chemin
        });
    }
    public function down(): void {
        Schema::table('matiere', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
