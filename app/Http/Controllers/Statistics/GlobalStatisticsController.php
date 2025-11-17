<?php
// app/Http/Controllers/Statistics/GlobalStatisticsController.php
namespace App\Http\Controllers\Statistics;

use App\Http\Controllers\Controller;
use App\Services\Statistics\GlobalStatisticsService;
use Illuminate\Http\JsonResponse;

class GlobalStatisticsController extends Controller
{
    public function __construct(private GlobalStatisticsService $service) {}

    public function basic(): JsonResponse
    {
        $data = $this->service->getBasicCounts();
        return response()->json($data);
    }
}
