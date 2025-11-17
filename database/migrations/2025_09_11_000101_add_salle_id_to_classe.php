<?php
// database/migrations/2025_09_11_000101_add_salle_id_to_classe.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('classe', function (Blueprint $table) {
      if (!Schema::hasColumn('classe','salle_id')) {
        $table->foreignId('salle_id')->nullable()->after('description')
              ->constrained('salles')->nullOnDelete();
      }
    });
  }
  public function down(): void {
    Schema::table('classe', function (Blueprint $table) {
      if (Schema::hasColumn('classe','salle_id')) {
        $table->dropConstrainedForeignId('salle_id');
      }
    });
  }
};
