<?php

namespace App\Services\Statistics;

use Illuminate\Support\Facades\DB;

class PaymentsStatisticsService
{
    // Revenus par MOIS d'une ANNEE donnée (inscription vs activite)
    public function getMonthlySeries(int $year): array
    {
        // Libellés (tu peux passer en FR "Janv", "Févr", ...)
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $base = array_map(fn($l) => [
            'label' => $l,
            'enrollment' => 0.0,   // = type 'inscription'
            'activity' => 0.0,   // = type 'activite'
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
            $idx = max(1, min(12, (int) $r->m)) - 1;
            $base[$idx]['enrollment'] = (float) ($r->enrollment ?? 0);
            $base[$idx]['activity'] = (float) ($r->activity ?? 0);
        }
        return $base;
    }

    // Revenus par ANNEE sur un intervalle [from..to] (inscription vs activite)
    public function getYearlySeries(int $from, int $to): array
    {
        if ($from > $to)
            [$from, $to] = [$to, $from];

        // Seed des années (pour renvoyer 0 si année sans data)
        $base = [];
        for ($y = $from; $y <= $to; $y++) {
            $base[$y] = [
                'label' => (string) $y,
                'enrollment' => 0.0,
                'activity' => 0.0,
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
                $base[$y] = ['label' => (string) $y, 'enrollment' => 0.0, 'activity' => 0.0];
            }
            $base[$y]['enrollment'] = (float) ($r->enrollment ?? 0);
            $base[$y]['activity'] = (float) ($r->activity ?? 0);
        }

        return array_values($base);
    }

    /**
     * Obtenir le revenu total avec comparaison 7 derniers jours
     * Format similaire à l'image fournie (Total Income avec pourcentage)
     */
    public function getTotalRevenue(): array
    {
        // Revenu total (tous les paiements confirmés)
        $totalRevenue = DB::table('paiements')
            ->where('statut', 'paye')
            ->sum('montant');

        // Revenu des 7 derniers jours
        $last7Days = DB::table('paiements')
            ->where('statut', 'paye')
            ->where('date_paiement', '>=', now()->subDays(7))
            ->sum('montant');

        // Revenu des 7 jours précédents (pour comparaison)
        $previous7Days = DB::table('paiements')
            ->where('statut', 'paye')
            ->whereBetween('date_paiement', [
                now()->subDays(14),
                now()->subDays(7)
            ])
            ->sum('montant');

        // Calcul du pourcentage de variation
        $percentageChange = 0;
        if ($previous7Days > 0) {
            $percentageChange = (($last7Days - $previous7Days) / $previous7Days) * 100;
        } elseif ($last7Days > 0) {
            $percentageChange = 100; // Nouvelle revenue
        }

        return [
            'total_revenue' => (float) $totalRevenue,
            'last_7_days_revenue' => (float) $last7Days,
            'percentage_change' => round($percentageChange, 2),
            'trend' => $percentageChange >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * Obtenir la liste des enfants avec leur statut de paiement
     * Affiche: nom enfant, statut paiement, date paiement
     */
    public function getChildrenPaymentStatus(): array
    {
        $children = DB::table('enfant')
            ->select(
                'enfant.id',
                'enfant.nom',
                'enfant.prenom',
                DB::raw('MAX(paiements.date_paiement) as derniere_date_paiement'),
                DB::raw('MAX(paiements.statut) as dernier_statut_paiement'),
                DB::raw('SUM(CASE WHEN paiements.statut = "paye" THEN paiements.montant ELSE 0 END) as total_paye'),
                DB::raw('SUM(CASE WHEN paiements.statut = "en_attente" THEN paiements.montant ELSE 0 END) as total_en_attente')
            )
            ->leftJoin('enfant_parent', 'enfant.id', '=', 'enfant_parent.enfant_id')
            ->leftJoin('parents as p', 'enfant_parent.parent_id', '=', 'p.id')
            ->leftJoin('inscriptions', function ($join) {
                $join->on('p.id', '=', 'inscriptions.parent_id');
            })
            ->leftJoin('paiements', 'inscriptions.id', '=', 'paiements.inscription_id')
            ->groupBy('enfant.id', 'enfant.nom', 'enfant.prenom')
            ->orderBy('enfant.nom')
            ->get();

        return $children->map(function ($child) {
            // Déterminer le statut de paiement global
            $paymentStatus = 'Non payé';
            $statusClass = 'danger';

            if ($child->dernier_statut_paiement === 'paye') {
                $paymentStatus = 'Payé';
                $statusClass = 'success';
            } elseif ($child->dernier_statut_paiement === 'en_attente') {
                $paymentStatus = 'En attente';
                $statusClass = 'warning';
            }

            return [
                'id' => $child->id,
                'nom_complet' => $child->prenom . ' ' . $child->nom,
                'statut_paiement' => $paymentStatus,
                'statut_class' => $statusClass,
                'date_paiement' => $child->derniere_date_paiement,
                'total_paye' => (float) ($child->total_paye ?? 0),
                'total_en_attente' => (float) ($child->total_en_attente ?? 0),
            ];
        })->toArray();
    }
}
