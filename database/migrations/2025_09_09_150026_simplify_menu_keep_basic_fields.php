<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            // on ne garde QUE: description, date_menu, type_repas, ingredients (+ id/timestamps)
            $table->dropColumn([
                'plat_principal',
                'accompagnement',
                'dessert',
                'boisson',
                'informations_nutritionnelles',
                'prix',
                'actif',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            $table->string('plat_principal')->nullable();
            $table->string('accompagnement')->nullable();
            $table->string('dessert')->nullable();
            $table->string('boisson')->nullable();
            $table->text('informations_nutritionnelles')->nullable();
            $table->decimal('prix', 8, 2)->nullable();
            $table->boolean('actif')->default(true);
        });
    }
};
