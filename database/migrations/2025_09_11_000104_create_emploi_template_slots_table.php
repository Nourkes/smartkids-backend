<?php
// database/migrations/2025_09_11_000104_create_emploi_template_slots_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('emploi_template_slots', function (Blueprint $table) {
      $table->id();
      $table->foreignId('emploi_template_id')->constrained('emploi_templates')->cascadeOnDelete();
      $table->unsignedTinyInteger('jour_semaine'); // 1..6
      $table->time('debut');
      $table->time('fin');
      $table->foreignId('matiere_id')->constrained('matiere')->cascadeOnDelete();
      $table->foreignId('educateur_id')->constrained('educateurs')->cascadeOnDelete();
      $table->foreignId('salle_id')->nullable()->constrained('salles')->nullOnDelete(); // NOUVEAU
      $table->string('status')->default('planned'); // planned|locked|cancelled
      $table->timestamps();

      $table->index(['jour_semaine','debut']);
      $table->index(['salle_id','jour_semaine','debut','fin'],'idx_salle_creneau');
    });
  }
  public function down(): void { Schema::dropIfExists('emploi_template_slots'); }
};
