<?php
// database/migrations/2025_09_23_000001_create_grades_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('grades', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('enfant_id');
            $t->unsignedBigInteger('classe_id');
            $t->unsignedBigInteger('matiere_id')->nullable(); // optionnel si note globale
            $t->unsignedBigInteger('teacher_id');             // l’éducateur qui saisit
            $t->string('school_year', 9);                     // "2024–2025"
            $t->unsignedTinyInteger('term');                  // 1 ou 2
            $t->string('grade', 2);                           // A/B/C/D (ou autre)
            $t->text('remark')->nullable();

            $t->timestamps();

            $t->foreign('enfant_id')->references('id')->on('enfants')->cascadeOnDelete();
            $t->foreign('classe_id')->references('id')->on('classes')->cascadeOnDelete();
            $t->foreign('matiere_id')->references('id')->on('matieres')->nullOnDelete();
            $t->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();

            $t->unique(['enfant_id','matiere_id','school_year','term'], 'grades_unique_by_term');
        });
    }

    public function down(): void {
        Schema::dropIfExists('grades');
    }
};
