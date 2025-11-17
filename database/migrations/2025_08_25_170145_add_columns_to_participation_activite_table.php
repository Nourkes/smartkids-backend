<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('participation_activite', function (Blueprint $table) {
            if (!Schema::hasColumn('participation_activite','statut_participation')) {
                $table->enum('statut_participation', ['inscrit','present','absent','excuse'])
                      ->default('inscrit')
                      ->after('enfant_id');
            }
            if (!Schema::hasColumn('participation_activite','remarques')) {
                $table->text('remarques')->nullable();
            }
            if (!Schema::hasColumn('participation_activite','note_evaluation')) {
                $table->unsignedTinyInteger('note_evaluation')->nullable();
            }
            if (!Schema::hasColumn('participation_activite','date_inscription')) {
                $table->date('date_inscription')->nullable();
            }
            if (!Schema::hasColumn('participation_activite','date_presence')) {
                $table->date('date_presence')->nullable();
            }
            if (!Schema::hasColumn('participation_activite','created_at')) {
                $table->timestamps(); // created_at & updated_at
            }
        });
    }

    public function down(): void
    {
        Schema::table('participation_activite', function (Blueprint $table) {
            $table->dropColumn([
                'statut_participation',
                'remarques',
                'note_evaluation',
                'date_inscription',
                'date_presence',
            ]);
            // $table->dropTimestamps();
        });
    }
};
