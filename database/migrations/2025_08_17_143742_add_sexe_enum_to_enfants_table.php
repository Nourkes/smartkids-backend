<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enfant', function (Blueprint $table) {
            $table->enum('sexe', ['garçon', 'fille'])->after('prenom');
        });
    }

    public function down(): void
    {
        Schema::table('enfants', function (Blueprint $table) {
            $table->dropColumn('sexe');
        });
    }
};
