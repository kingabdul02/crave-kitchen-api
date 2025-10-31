<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Item;
use App\Models\OrderItem;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardService = new DashboardService();
        Cache::flush(); // Clear cache before each test
    }

    public function test_can_get_dashboard_metrics()
    {
        // Create test data for current period
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 100.00]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100.00,
            'created_at' => Carbon::now()->subDays(5), // Within current period
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'total_price' => 100.00,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $metrics = $this->dashboardService->getDashboardMetrics();

        $this->assertArrayHasKey('current_period', $metrics);
        $this->assertArrayHasKey('previous_period', $metrics);
        $this->assertArrayHasKey('trends', $metrics);
        $this->assertArrayHasKey('charts_data', $metrics);
        $this->assertArrayHasKey('last_updated', $metrics);

        // Verify current period data
        $currentPeriod = $metrics['current_period'];
        $this->assertEquals(1, $currentPeriod['orders']['total']);
        $this->assertEquals(1, $currentPeriod['orders']['completed']);
        $this->assertEquals(100.00, $currentPeriod['revenue']['total']);
        $this->assertEquals(100.00, $currentPeriod['revenue']['average_order_value']);
        $this->assertEquals(1, $currentPeriod['customers']['new']);
        $this->assertEquals(1, $currentPeriod['customers']['active']);
    }

    public function test_can_get_recent_activity()
    {
        // Create test data
        $customer = Customer::factory()->create(['name' => 'John Doe']);
        $item = Item::factory()->create(['stock_quantity' => 5]); // Low stock

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-001',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'payment_method' => 'cash',
        ]);

        $activity = $this->dashboardService->getRecentActivity();

        $this->assertArrayHasKey('recent_orders', $activity);
        $this->assertArrayHasKey('recent_payments', $activity);
        $this->assertArrayHasKey('low_stock_items', $activity);
        $this->assertArrayHasKey('last_updated', $activity);

        // Verify recent orders
        $this->assertCount(1, $activity['recent_orders']);
        $this->assertEquals('ORD-001', $activity['recent_orders'][0]['order_number']);
        $this->assertEquals('John Doe', $activity['recent_orders'][0]['customer_name']);

        // Verify recent payments
        $this->assertCount(1, $activity['recent_payments']);
        $this->assertEquals(50.00, $activity['recent_payments'][0]['amount']);
        $this->assertEquals('cash', $activity['recent_payments'][0]['payment_method']);

        // Verify low stock items
        $this->assertCount(1, $activity['low_stock_items']);
        $this->assertEquals(5, $activity['low_stock_items'][0]['stock_quantity']);
    }

    public function test_calculates_trends_correctly()
    {
        // Create data for previous period (40 days ago)
        $customer1 = Customer::factory()->create();
        $order1 = Order::factory()->create([
            'customer_id' => $customer1->id,
            'order_number' => 'ORD-PREV-001',
            'status' => 'completed',
            'total_amount' => 50.00,
            'created_at' => Carbon::now()->subDays(40),
        ]);

        // Create data for current period (10 days ago)
        $customer2 = Customer::factory()->create();
        $order2 = Order::factory()->create([
            'customer_id' => $customer2->id,
            'order_number' => 'ORD-CURR-001',
            'status' => 'completed',
            'total_amount' => 100.00,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $order3 = Order::factory()->create([
            'customer_id' => $customer2->id,
            'order_number' => 'ORD-CURR-002',
            'status' => 'completed',
            'total_amount' => 100.00,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $metrics = $this->dashboardService->getDashboardMetrics();
        $trends = $metrics['trends'];

        // Should show 100% increase in orders (1 -> 2)
        $this->assertEquals(100.0, $trends['orders']['total']);

        // Should show 300% increase in revenue (50 -> 200)
        $this->assertEquals(300.0, $trends['revenue']['total']);
    }

    public function test_handles_zero_division_in_trends()
    {
        // Create data only for current period
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'total_amount' => 100.00,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $metrics = $this->dashboardService->getDashboardMetrics();
        $trends = $metrics['trends'];

        // Should handle zero division gracefully
        $this->assertEquals(100.0, $trends['orders']['total']); // 100% when previous is 0
        $this->assertEquals(100.0, $trends['revenue']['total']);
    }

    public function test_charts_data_covers_last_7_days()
    {
        // Create orders for different days
        $customer = Customer::factory()->create();

        for ($i = 0; $i < 7; $i++) {
            Order::factory()->create([
                'customer_id' => $customer->id,
                'order_number' => 'ORD-CHART-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'status' => 'completed',
                'total_amount' => 100.00,
                'created_at' => Carbon::now()->subDays($i)->startOfDay(),
            ]);
        }

        $metrics = $this->dashboardService->getDashboardMetrics();
        $chartsData = $metrics['charts_data'];

        $this->assertCount(7, $chartsData['labels']);
        $this->assertCount(7, $chartsData['orders']);
        $this->assertCount(7, $chartsData['revenue']);

        // Each day should have 1 order
        foreach ($chartsData['orders'] as $orderCount) {
            $this->assertEquals(1, $orderCount);
        }

        // Each day should have 100.00 revenue
        foreach ($chartsData['revenue'] as $revenue) {
            $this->assertEquals(100.00, $revenue);
        }
    }

    public function test_low_stock_items_filters_correctly()
    {
        // Create items with different stock levels
        Item::factory()->create(['stock_quantity' => 15, 'is_active' => true, 'name' => 'High Stock Item']);
        Item::factory()->create(['stock_quantity' => 5, 'is_active' => true, 'name' => 'Low Stock Item 1']);
        Item::factory()->create(['stock_quantity' => 2, 'is_active' => true, 'name' => 'Low Stock Item 2']);
        Item::factory()->create(['stock_quantity' => 0, 'is_active' => true, 'name' => 'Out of Stock Item']);
        Item::factory()->create(['stock_quantity' => 5, 'is_active' => false, 'name' => 'Inactive Item']);

        $activity = $this->dashboardService->getRecentActivity();
        $lowStockItems = $activity['low_stock_items'];

        // Should only include active items with stock <= 10
        $this->assertCount(3, $lowStockItems);

        $itemNames = array_column($lowStockItems, 'name');
        $this->assertContains('Low Stock Item 1', $itemNames);
        $this->assertContains('Low Stock Item 2', $itemNames);
        $this->assertContains('Out of Stock Item', $itemNames);
        $this->assertNotContains('High Stock Item', $itemNames);
        $this->assertNotContains('Inactive Item', $itemNames);

        // Should be ordered by stock quantity (ascending)
        $this->assertEquals('Out of Stock Item', $lowStockItems[0]['name']);
        $this->assertEquals(0, $lowStockItems[0]['stock_quantity']);
    }

    public function test_cache_is_used()
    {
        // First call should cache the result
        $metrics1 = $this->dashboardService->getDashboardMetrics();

        // Second call should return cached result
        $metrics2 = $this->dashboardService->getDashboardMetrics();

        // Both should have the same timestamp
        $this->assertEquals($metrics1['last_updated'], $metrics2['last_updated']);
    }

    public function test_can_clear_cache()
    {
        // Cache some data
        $this->dashboardService->getDashboardMetrics();
        $this->dashboardService->getRecentActivity();

        // Verify cache exists
        $this->assertTrue(Cache::has('dashboard_metrics'));
        $this->assertTrue(Cache::has('dashboard_recent_activity'));

        // Clear cache
        $this->dashboardService->clearCache();

        // Verify cache is cleared
        $this->assertFalse(Cache::has('dashboard_metrics'));
        $this->assertFalse(Cache::has('dashboard_recent_activity'));
    }
}
