<?php

namespace App\Services\Statistics;

use Illuminate\Support\Facades\DB;

class PaymentsStatisticsService
{
    // Revenus par MOIS d'une ANNEE donnée (inscription vs activite)
    public function getMonthlySeries(int $year): array
    {
        // Libellés (tu peux passer en FR "Janv", "Févr", ...)
        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $base = array_map(fn($l) => [
            'label'      => $l,
            'enrollment' => 0.0,   // = type 'inscription'
            'activity'   => 0.0,   // = type 'activite'
        ], $labels);

        $rows = DB::table('paiements')
            ->selectRaw("
                MONTH(date_paiement) as m,
                SUM(CASE WHEN type = 'inscription' THEN montant ELSE 0 END) as enrollment,
                SUM(CASE WHEN type = 'activite'   THEN montant ELSE 0 END) as activity
            ")
            ->whereYear('date_paiement', $year)
            ->where('statut', 'paye')
            ->groupByRaw('MONTH(date_paiement)')
            ->orderByRaw('MONTH(date_paiement)')
            ->get();

        foreach ($rows as $r) {
            $idx = max(1, min(12, (int)$r->m)) - 1;
            $base[$idx]['enrollment'] = (float) ($r->enrollment ?? 0);
            $base[$idx]['activity']   = (float) ($r->activity   ?? 0);
        }
        return $base;
    }

    // Revenus par ANNEE sur un intervalle [from..to] (inscription vs activite)
    public function getYearlySeries(int $from, int $to): array
    {
        if ($from > $to) [$from, $to] = [$to, $from];

        // Seed des années (pour renvoyer 0 si année sans data)
        $base = [];
        for ($y = $from; $y <= $to; $y++) {
            $base[$y] = [
                'label'      => (string)$y,
                'enrollment' => 0.0,
                'activity'   => 0.0,
            ];
        }

        $rows = DB::table('paiements')
            ->selectRaw("
                YEAR(date_paiement) as y,
                SUM(CASE WHEN type = 'inscription' THEN montant ELSE 0 END) as enrollment,
                SUM(CASE WHEN type = 'activite'   THEN montant ELSE 0 END) as activity
            ")
            ->whereBetween(DB::raw('YEAR(date_paiement)'), [$from, $to])
            ->where('statut', 'paye')
            ->groupByRaw('YEAR(date_paiement)')
            ->orderByRaw('YEAR(date_paiement)')
            ->get();

        foreach ($rows as $r) {
            $y = (int) $r->y;
            if (!isset($base[$y])) {
                $base[$y] = ['label' => (string)$y, 'enrollment' => 0.0, 'activity' => 0.0];
            }
            $base[$y]['enrollment'] = (float) ($r->enrollment ?? 0);
            $base[$y]['activity']   = (float) ($r->activity   ?? 0);
        }

        return array_values($base);
    }
}
