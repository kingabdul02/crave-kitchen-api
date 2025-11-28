<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get dashboard metrics and analytics
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            $metrics = $this->dashboardService->getDashboardMetrics($dateFrom, $dateTo);

            return $this->successResponse($metrics, 'Dashboard metrics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard metrics', 500);
        }
    }

    /**
     * Get recent activity for dashboard
     */
    public function recentActivity(): JsonResponse
    {
        try {
            $activity = $this->dashboardService->getRecentActivity();

            return $this->successResponse($activity, 'Recent activity retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve recent activity', 500);
        }
    }
}
