<?php

namespace App\Http\Controllers\Statistics;

use App\Http\Controllers\Controller;
use App\Services\Statistics\PaymentsStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentsStatisticsController extends Controller
{
    public function __construct(private PaymentsStatisticsService $service)
    {
    }

    public function revenueSeries(Request $req): JsonResponse
    {
        $period = $req->query('period', 'monthly'); // 'monthly' | 'yearly'

        if ($period === 'monthly') {
            $year = (int) $req->query('year', date('Y'));
            $points = $this->service->getMonthlySeries($year);
            return response()->json([
                'period' => 'monthly',
                'year' => $year,
                'points' => $points,
            ]);
        }

        // yearly
        $from = (int) $req->query('from', date('Y') - 3);
        $to = (int) $req->query('to', date('Y'));
        $points = $this->service->getYearlySeries($from, $to);
        return response()->json([
            'period' => 'yearly',
            'from' => $from,
            'to' => $to,
            'points' => $points,
        ]);
    }

    /**
     * Vue d'ensemble financière pour le dashboard admin
     * Retourne: revenu total, comparaison 7 jours, et liste des enfants avec statut paiement
     */
    public function financialOverview(Request $req): JsonResponse
    {
        $revenue = $this->service->getTotalRevenue();
        $childrenPayments = $this->service->getChildrenPaymentStatus();

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => $revenue,
                'children_payments' => $childrenPayments,
            ],
        ]);
    }
}

