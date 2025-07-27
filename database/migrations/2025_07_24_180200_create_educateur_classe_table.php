<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('educateur_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('educateur_id')->constrained('educateurs')->onDelete('cascade');
            $table->foreignId('classe_id')->constrained('classe')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['educateur_id', 'classe_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('educateur_classe');
    }
};