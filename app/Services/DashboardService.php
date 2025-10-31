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
    public function getDashboardMetrics(): array
    {
        return Cache::remember('dashboard_metrics', 300, function () { // Cache for 5 minutes
            $currentPeriod = $this->getCurrentPeriodMetrics();
            $previousPeriod = $this->getPreviousPeriodMetrics();

            return [
                'current_period' => $currentPeriod,
                'previous_period' => $previousPeriod,
                'trends' => $this->calculateTrends($currentPeriod, $previousPeriod),
                'charts_data' => $this->getChartsData(),
                'last_updated' => now()->toISOString()
            ];
        });
    }

    /**
     * Get recent activity with caching
     */
    public function getRecentActivity(): array
    {
        return Cache::remember('dashboard_recent_activity', 60, function () { // Cache for 1 minute
            return [
                'recent_orders' => $this->getRecentOrders(),
                'recent_payments' => $this->getRecentPayments(),
                'low_stock_items' => $this->getLowStockItems(),
                'last_updated' => now()->toISOString()
            ];
        });
    }

    /**
     * Get current period metrics (last 30 days)
     */
    private function getCurrentPeriodMetrics(): array
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        return $this->getPeriodMetrics($startDate, $endDate);
    }

    /**
     * Get previous period metrics (30-60 days ago)
     */
    private function getPreviousPeriodMetrics(): array
    {
        $startDate = Carbon::now()->subDays(60);
        $endDate = Carbon::now()->subDays(30);

        return $this->getPeriodMetrics($startDate, $endDate);
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
     * Get data for charts (last 7 days)
     */
    private function getChartsData(): array
    {
        $days = collect();
        $ordersData = collect();
        $revenueData = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $days->push($date->format('M j'));

            // Orders count for the day
            $ordersCount = Order::whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $ordersData->push($ordersCount);

            // Revenue for the day
            $revenue = Order::whereBetween('created_at', [$dayStart, $dayEnd])
                ->whereIn('status', ['completed', 'processed'])
                ->sum('total_amount');
            $revenueData->push(round($revenue, 2));
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
        Cache::forget('dashboard_metrics');
        Cache::forget('dashboard_recent_activity');
    }
}
