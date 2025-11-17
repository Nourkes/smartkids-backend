<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            if (Schema::hasColumn('menu', 'nom_menu')) {
                $table->renameColumn('nom_menu', 'description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            if (Schema::hasColumn('menu', 'description')) {
                $table->renameColumn('description', 'nom_menu');
            }
        });
    }
};
