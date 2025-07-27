<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('activite', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->enum('type', ['musique', 'peinture', 'sport', 'lecture', 'sortie', 'autre']);
            $table->date('date_activite');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->decimal('prix', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('activite');
    }
};