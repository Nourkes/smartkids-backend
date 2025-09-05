<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('participation_activite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enfant_id')->constrained('enfant')->onDelete('cascade');
            $table->foreignId('activite_id')->constrained('activite')->onDelete('cascade');
            $table->timestamps();
            
            // Index unique pour éviter les doublons
            $table->unique(['enfant_id', 'activite_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('participation_activite');
    }
};