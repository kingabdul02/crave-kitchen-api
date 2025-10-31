<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Item;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Cache::flush(); // Clear cache before each test
    }

    public function test_can_get_dashboard_metrics()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 100.00]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100.00,
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
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_period' => [
                        'orders' => [
                            'total',
                            'pending',
                            'processed',
                            'completed',
                            'cancelled',
                        ],
                        'revenue' => [
                            'total',
                            'paid',
                            'pending',
                            'average_order_value',
                        ],
                        'payments' => [
                            'total_amount',
                            'count',
                        ],
                        'customers' => [
                            'new',
                            'active',
                        ],
                    ],
                    'previous_period',
                    'trends',
                    'charts_data' => [
                        'labels',
                        'orders',
                        'revenue',
                    ],
                    'last_updated',
                ],
            ]);

        // Verify the data contains expected values
        $data = $response->json('data');
        $this->assertEquals(1, $data['current_period']['orders']['total']);
        $this->assertEquals(1, $data['current_period']['orders']['completed']);
        $this->assertEquals(100.00, $data['current_period']['revenue']['total']);
        $this->assertEquals(1, $data['current_period']['customers']['new']);
    }

    public function test_can_get_recent_activity()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['stock_quantity' => 5]); // Low stock item

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
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/dashboard/recent-activity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'recent_orders' => [
                        '*' => [
                            'id',
                            'order_number',
                            'customer_name',
                            'status',
                            'payment_status',
                            'total_amount',
                            'created_at',
                            'items_count',
                        ],
                    ],
                    'recent_payments' => [
                        '*' => [
                            'id',
                            'order_number',
                            'customer_name',
                            'amount',
                            'payment_method',
                            'created_at',
                        ],
                    ],
                    'low_stock_items' => [
                        '*' => [
                            'id',
                            'name',
                            'category',
                            'stock_quantity',
                            'price',
                        ],
                    ],
                    'last_updated',
                ],
            ]);

        // Verify the data contains expected values
        $data = $response->json('data');
        $this->assertCount(1, $data['recent_orders']);
        $this->assertEquals('ORD-001', $data['recent_orders'][0]['order_number']);
        $this->assertCount(1, $data['recent_payments']);
        $this->assertEquals(50.00, $data['recent_payments'][0]['amount']);
        $this->assertCount(1, $data['low_stock_items']);
        $this->assertEquals(5, $data['low_stock_items'][0]['stock_quantity']);
    }

    public function test_dashboard_metrics_are_cached()
    {
        $response1 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response2 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Both responses should have the same last_updated timestamp due to caching
        $this->assertEquals(
            $response1->json('data.last_updated'),
            $response2->json('data.last_updated')
        );
    }

    public function test_recent_activity_is_cached()
    {
        $response1 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/dashboard/recent-activity');

        $response2 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/dashboard/recent-activity');

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Both responses should have the same last_updated timestamp due to caching
        $this->assertEquals(
            $response1->json('data.last_updated'),
            $response2->json('data.last_updated')
        );
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/admin/dashboard');
        $response->assertStatus(401);

        $response = $this->getJson('/api/admin/dashboard/recent-activity');
        $response->assertStatus(401);
    }

    public function test_handles_empty_data_gracefully()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(0, $data['current_period']['orders']['total']);
        $this->assertEquals(0, $data['current_period']['revenue']['total']);
        $this->assertEquals(0, $data['current_period']['customers']['new']);
    }
}
