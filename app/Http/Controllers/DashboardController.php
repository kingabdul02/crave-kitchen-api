<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

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
    public function index(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getDashboardMetrics();

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
