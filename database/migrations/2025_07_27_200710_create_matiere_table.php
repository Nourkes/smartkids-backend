<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('matiere', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('code')->unique(); // Ex: MATH01, FRAN01
            $table->enum('niveau', ['petite_section', 'moyenne_section', 'grande_section']);
            $table->integer('coefficient')->default(1);
            $table->string('couleur')->nullable(); // Pour l'affichage (ex: #FF5733)
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('matiere');
    }
};