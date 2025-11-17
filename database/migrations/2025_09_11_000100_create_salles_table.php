<?php
// database/migrations/2025_09_11_000100_create_salles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('salles', function (Blueprint $table) {
      $table->id();
      $table->string('code')->unique();         // ex: A101, MUS1
      $table->string('nom');                    // ex: Classe Oursons
      $table->unsignedSmallInteger('capacite')->nullable();
      $table->boolean('is_active')->default(true);
      $table->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('salles'); }
};
