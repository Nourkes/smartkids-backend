<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('liste_attente', function (Blueprint $table) {
            $table->id();
            $table->string('nom_enfant');
            $table->string('prenom_enfant');
            $table->date('date_naissance_enfant');
            $table->string('nom_parent');
            $table->string('telephone_parent');
            $table->string('email_parent');
            $table->date('date_demande');
            $table->integer('position')->nullable(); // Position dans la liste
            $table->enum('statut', ['en_attente', 'contacte', 'inscrit', 'refuse'])->default('en_attente');
            $table->text('remarques')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('liste_attente');
    }
};