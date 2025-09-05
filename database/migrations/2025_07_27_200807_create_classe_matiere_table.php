<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('classe_matiere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classe')->onDelete('cascade');
            $table->foreignId('matiere_id')->constrained('matiere')->onDelete('cascade');
            $table->integer('heures_par_semaine')->default(1);
            $table->text('objectifs_specifiques')->nullable();
            $table->timestamps();
            
            // Index unique pour éviter les doublons
            $table->unique(['classe_id', 'matiere_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('classe_matiere');
    }
};