<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('presence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enfant_id')->constrained('enfant')->onDelete('cascade');
            $table->foreignId('educateur_id')->constrained('educateurs')->onDelete('cascade');
            $table->date('date_presence');
            $table->enum('statut', ['present', 'absent'])->default('absent');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('presence');
    }
};