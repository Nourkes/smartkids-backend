<?php
// database/migrations/2025_09_11_000103_create_emploi_templates_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('emploi_templates', function (Blueprint $table) {
      $table->id();
      $table->foreignId('classe_id')->constrained('classe')->cascadeOnDelete();
      $table->date('period_start');           // début année scolaire
      $table->date('period_end');             // fin année scolaire
      $table->date('effective_from');         // date d’entrée en vigueur de cette version
      $table->string('status')->default('draft'); // draft|published
      $table->unsignedInteger('version')->default(1);
      $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();

      $table->unique(['classe_id','version']);
      $table->index(['classe_id','status','effective_from']);
    });
  }
  public function down(): void { Schema::dropIfExists('emploi_templates'); }
};
