<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('classe', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->integer('niveau'); // 1, 2, 3 pour différents niveaux
            $table->integer('capacite_max')->default(25);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('classe');
    }
};