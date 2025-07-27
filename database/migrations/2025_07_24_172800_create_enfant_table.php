<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('enfant', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->date('date_naissance');
            $table->foreignId('classe_id')->nullable()->constrained('classe')->onDelete('set null');
            $table->text('allergies')->nullable();
            $table->text('remarques_medicales')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('enfant');
    }
};