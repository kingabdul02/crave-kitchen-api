<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get comprehensive dashboard metrics with caching
     */
    public function getDashboardMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $startDate = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $endDate = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();

        // Get cache timestamp for invalidation
        $cacheTimestamp = Cache::get('dashboard_cache_timestamp', 0);
        
        // Create cache key based on date range and timestamp
        $cacheKey = 'dashboard_metrics_' . $startDate->timestamp . '_' . $endDate->timestamp . '_' . $cacheTimestamp;

        return Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) { // Cache for 5 minutes
            $currentPeriod = $this->getCurrentPeriodMetrics($startDate, $endDate);
            $previousPeriod = $this->getPreviousPeriodMetrics($startDate, $endDate);

            return [
                'current_period' => $currentPeriod,
                'previous_period' => $previousPeriod,
                'trends' => $this->calculateTrends($currentPeriod, $previousPeriod),
                'charts_data' => $this->getChartsData($startDate, $endDate),
                'last_updated' => now()->toISOString()
            ];
        });
    }

    /**
     * Get recent activity with caching
     */
    public function getRecentActivity(): array
    {
        // Get cache timestamp for invalidation
        $cacheTimestamp = Cache::get('dashboard_cache_timestamp', 0);
        $cacheKey = 'dashboard_recent_activity_' . $cacheTimestamp;

        return Cache::remember($cacheKey, 60, function () { // Cache for 1 minute
            return [
                'recent_orders' => $this->getRecentOrders(),
                'recent_payments' => $this->getRecentPayments(),
                'low_stock_items' => $this->getLowStockItems(),
                'last_updated' => now()->toISOString()
            ];
        });
    }

    /**
     * Get current period metrics
     */
    private function getCurrentPeriodMetrics(Carbon $startDate, Carbon $endDate): array
    {
        return $this->getPeriodMetrics($startDate, $endDate);
    }

    /**
     * Get previous period metrics (same duration as current period, immediately before it)
     */
    private function getPreviousPeriodMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $daysDiff = $startDate->diffInDays($endDate);
        $previousStartDate = $startDate->copy()->subDays($daysDiff + 1);
        $previousEndDate = $startDate->copy()->subSeconds(1);

        return $this->getPeriodMetrics($previousStartDate, $previousEndDate);
    }

    /**
     * Get metrics for a specific period
     */
    private function getPeriodMetrics(Carbon $startDate, Carbon $endDate): array
    {
        // Order metrics
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $pendingOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'pending')
            ->count();
        $processedOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'processed')
            ->count();
        $completedOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->count();
        $cancelledOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'cancelled')
            ->count();

        // Revenue metrics
        $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'processed'])
            ->sum('total_amount');

        $paidRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $pendingRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->sum('total_amount');

        // Settlement metrics
        $settledRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('is_settled', true)
            ->sum('total_amount');

        $unsettledRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('is_settled', false)
            ->sum('total_amount');

        // Payment metrics
        $totalPayments = Payment::whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        $paymentsCount = Payment::whereBetween('created_at', [$startDate, $endDate])->count();

        // Customer metrics
        $allCustomers = Customer::count();
        $newCustomers = Customer::whereBetween('created_at', [$startDate, $endDate])->count();
        $activeCustomers = Order::whereBetween('created_at', [$startDate, $endDate])
            ->distinct('customer_id')
            ->count();

        // Average order value
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return [
            'orders' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
                'processed' => $processedOrders,
                'completed' => $completedOrders,
                'cancelled' => $cancelledOrders,
            ],
            'revenue' => [
                'total' => round($totalRevenue, 2),
                'paid' => round($paidRevenue, 2),
                'pending' => round($pendingRevenue, 2),
                'settled' => round($settledRevenue, 2),
                'unsettled' => round($unsettledRevenue, 2),
                'average_order_value' => round($averageOrderValue, 2),
            ],
            'payments' => [
                'total_amount' => round($totalPayments, 2),
                'count' => $paymentsCount,
            ],
            'customers' => [
                'all' => $allCustomers,
                'new' => $newCustomers,
                'active' => $activeCustomers,
            ],
        ];
    }

    /**
     * Calculate trends between current and previous periods
     */
    private function calculateTrends(array $current, array $previous): array
    {
        return [
            'orders' => [
                'total' => $this->calculatePercentageChange($current['orders']['total'], $previous['orders']['total']),
                'pending' => $this->calculatePercentageChange($current['orders']['pending'], $previous['orders']['pending']),
                'completed' => $this->calculatePercentageChange($current['orders']['completed'], $previous['orders']['completed']),
            ],
            'revenue' => [
                'total' => $this->calculatePercentageChange($current['revenue']['total'], $previous['revenue']['total']),
                'settled' => $this->calculatePercentageChange($current['revenue']['settled'], $previous['revenue']['settled']),
                'unsettled' => $this->calculatePercentageChange($current['revenue']['unsettled'], $previous['revenue']['unsettled']),
                'average_order_value' => $this->calculatePercentageChange($current['revenue']['average_order_value'], $previous['revenue']['average_order_value']),
            ],
            'customers' => [
                'new' => $this->calculatePercentageChange($current['customers']['new'], $previous['customers']['new']),
                'active' => $this->calculatePercentageChange($current['customers']['active'], $previous['customers']['active']),
            ],
        ];
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculatePercentageChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get data for charts
     */
    private function getChartsData(Carbon $startDate, Carbon $endDate): array
    {
        $days = collect();
        $ordersData = collect();
        $revenueData = collect();

        // Determine interval based on date range duration
        $diffInDays = $startDate->diffInDays($endDate);
        
        // If range is large (> 60 days), group by week or month could be better, 
        // but for now let's stick to daily or limit points if too many.
        // For simplicity, we'll iterate daily but if > 30 days, maybe we should group?
        // Let's stick to daily for now as requested.
        
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();

            $days->push($currentDate->format('M j'));

            // Orders count for the day
            $ordersCount = Order::whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $ordersData->push($ordersCount);

            // Revenue for the day
            $revenue = Order::whereBetween('created_at', [$dayStart, $dayEnd])
                ->whereIn('status', ['completed', 'processed'])
                ->sum('total_amount');
            $revenueData->push(round($revenue, 2));
            
            $currentDate->addDay();
        }

        return [
            'labels' => $days->toArray(),
            'orders' => $ordersData->toArray(),
            'revenue' => $revenueData->toArray(),
        ];
    }

    /**
     * Get recent orders (last 10)
     */
    private function getRecentOrders(): array
    {
        return Order::with(['customer', 'orderItems.item'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer->name,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at->toISOString(),
                    'items_count' => $order->orderItems->count(),
                ];
            })
            ->toArray();
    }

    /**
     * Get recent payments (last 10)
     */
    private function getRecentPayments(): array
    {
        return Payment::with(['order.customer'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'order_number' => $payment->order->order_number,
                    'customer_name' => $payment->order->customer->name,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'created_at' => $payment->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get items with low stock (stock <= 10)
     */
    private function getLowStockItems(): array
    {
        return Item::where('stock_quantity', '<=', 10)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('stock_quantity', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category,
                    'stock_quantity' => $item->stock_quantity,
                    'price' => $item->price,
                ];
            })
            ->toArray();
    }

    /**
     * Clear dashboard cache
     */
    public function clearCache(): void
    {
        Cache::forget('dashboard_cache_timestamp');
        Cache::put('dashboard_cache_timestamp', now()->timestamp);
    }
}
