<?php
namespace App\Support;

use Carbon\Carbon;

class AcademicCalendar
{
    public static function yearStart(string $anneeCode): Carbon
    {
        $date = config("smartkids.academic_years.$anneeCode");
        abort_if(!$date, 500, "Start date absente pour $anneeCode (config/smartkids.php).");
        return Carbon::parse($date)->startOfDay();
    }

    public static function periodIndexForDate(Carbon $yearStart, int $N, Carbon $date): ?int
    {
        if ($date->lt($yearStart)) return null;
        $months = $yearStart->diffInMonths($date);
        $pStart = (clone $yearStart)->addMonthsNoOverflow($months);
        if ($date->lt($pStart)) $months--;
        return ($months >= 0 && $months < $N) ? $months : null;
    }

    public static function periodBounds(Carbon $yearStart, int $idx): array
    {
        $p0 = (clone $yearStart)->addMonthsNoOverflow($idx);
        $p1 = (clone $yearStart)->addMonthsNoOverflow($idx + 1);
        return [$p0, $p1]; // [start, endExclusive]
    }

    /** Semaines ISO (lundi→dimanche) clippées aux bornes du mois de $anyDate */
    public static function monthWeekSegments(Carbon $anyDate): array
    {
        $monthStart = $anyDate->copy()->startOfMonth();
        $monthEnd   = $anyDate->copy()->endOfMonth(); // inclusif
        $cursor     = $monthStart->copy()->startOfWeek(Carbon::MONDAY);

        $segments = [];
        while ($cursor->lte($monthEnd)) {
            $weekStart = $cursor->copy();                             // lundi
            $weekEnd   = $cursor->copy()->endOfWeek(Carbon::SUNDAY);  // dimanche
            $segStart  = $weekStart->lt($monthStart) ? $monthStart->copy() : $weekStart;
            $segEnd    = $weekEnd->gt($monthEnd)     ? $monthEnd->copy()   : $weekEnd;
            $segments[] = [$segStart, $segEnd];
            $cursor->addWeek();
        }
        return $segments; // ex: Oct 2025 => [ [1..5], [6..12], [13..19], [20..26], [27..31] ]
    }

    public static function weeksInMonthIso(Carbon $anyDate): int
    {
        return count(self::monthWeekSegments($anyDate)); // 4..6
    }

    public static function weekIndexInMonthIso(Carbon $date): int
    {
        foreach (self::monthWeekSegments($date) as $i => [$s, $e]) {
            if ($date->betweenIncluded($s, $e)) return $i + 1; // 1..N
        }
        return 1;
    }
}
