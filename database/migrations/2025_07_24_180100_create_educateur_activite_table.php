<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('educateur_activite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('educateur_id')->constrained('educateurs')->onDelete('cascade');
            $table->foreignId('activite_id')->constrained('activite')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['educateur_id', 'activite_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('educateur_activite');
    }
};