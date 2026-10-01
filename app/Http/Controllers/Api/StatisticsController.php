<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    private StatisticsService $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Get statistics for requested entities
     */
    public function index(Request $request)
    {
        try {
            $entities = $request->get('entities', '');
            $entityList = array_filter(array_map('trim', explode(',', $entities)));
            
            $statistics = $this->statisticsService->getStatistics($entityList);
            
            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}