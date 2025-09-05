<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('menu', function (Blueprint $table) {
            $table->id();
            $table->string('nom_menu');
            $table->date('date_menu');
            $table->enum('type_repas', ['petit_dejeuner', 'dejeuner', 'gouter', 'diner']);
            $table->text('plat_principal');
            $table->text('accompagnement')->nullable();
            $table->text('dessert')->nullable();
            $table->text('boisson')->nullable();
            $table->text('ingredients')->nullable(); // Pour les allergies
            $table->text('informations_nutritionnelles')->nullable();
            $table->decimal('prix', 8, 2)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('menu');
    }
};