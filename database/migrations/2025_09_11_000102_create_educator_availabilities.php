<?php
// database/migrations/2025_09_11_000102_create_educator_availabilities.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('educator_availabilities', function (Blueprint $table) {
      $table->id();
      $table->foreignId('educateur_id')->constrained('educateurs')->cascadeOnDelete();
      $table->unsignedTinyInteger('jour_semaine'); // 1=Lun ... 6=Sam
      $table->time('debut');
      $table->time('fin');
      $table->timestamps();
      $table->index(['educateur_id','jour_semaine']);
    });
  }
  public function down(): void { Schema::dropIfExists('educator_availabilities'); }
};
