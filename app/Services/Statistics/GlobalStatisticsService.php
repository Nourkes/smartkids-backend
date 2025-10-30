<?php
// app/Services/Statistics/GlobalStatisticsService.php

namespace App\Services\Statistics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use App\Models\Enfant;
use App\Models\Educateur;
use App\Models\Classe; // ou Classroom

class GlobalStatisticsService
{
    public function getBasicCounts(): array
    {
        return Cache::remember('stats.basic_counts', 300, function () {
            // Si tu as des colonnes "is_active", filtre-les ici.
            $students  = Enfant::count();
            $teachers  = Educateur::count();
            $classes   = classe::count();

            return [
                'students_total'  => $students,
                'teachers_total'  => $teachers,
                'classes_total'   => $classes,
                'last_updated'    => Carbon::now()->toIso8601String(),
            ];
        });
    }
}
