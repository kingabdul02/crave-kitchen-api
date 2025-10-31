<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test that order totals remain consistent after updates
     */
    public function test_order_total_consistency_after_item_updates()
    {
        $customer = Customer::factory()->create();
        $item1 = Item::factory()->create(['price' => 25.00]);
        $item2 = Item::factory()->create(['price' => 15.00]);

        // Create order
        $response = $this->actingAs($this->user)->postJson('/api/admin/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item1->id, 'quantity' => 2, 'unit_price' => 25.00],
                ['item_id' => $item2->id, 'quantity' => 3, 'unit_price' => 15.00],
            ],
        ]);

        $response->assertStatus(201);
        $order = Order::first();

        $this->assertEquals('95.00', $order->total_amount);
        $this->assertEquals('95.00', $order->subtotal);

        // Update order items
        $response = $this->actingAs($this->user)->putJson("/api/admin/orders/{$order->id}", [
            'items' => [
                ['item_id' => $item1->id, 'quantity' => 5, 'unit_price' => 25.00],
            ],
        ]);

        $response->assertStatus(200);
        $order->refresh();

        // Verify totals are recalculated correctly
        $this->assertEquals('125.00', $order->total_amount);
        $this->assertEquals('125.00', $order->subtotal);
        $this->assertCount(1, $order->orderItems);
    }

    /**
     * Test payment status consistency
     */
    public function test_payment_status_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 100.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ]);

        $order = Order::first();
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals(0, $order->total_paid);

        // Add partial payment
        $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 40.00,
            'payment_method' => 'cash',
        ]);

        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);
        $this->assertEquals(40.00, $order->total_paid);
        $this->assertEquals(60.00, $order->remaining_balance);

        // Add another partial payment
        $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 30.00,
            'payment_method' => 'transfer',
        ]);

        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);
        $this->assertEquals(70.00, $order->total_paid);
        $this->assertEquals(30.00, $order->remaining_balance);

        // Complete payment
        $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 30.00,
            'payment_method' => 'pos',
        ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(100.00, $order->total_paid);
        $this->assertEquals(0.00, $order->remaining_balance);
        $this->assertTrue($order->is_fully_paid);
    }

    /**
     * Test customer statistics consistency
     */
    public function test_customer_statistics_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        // Create multiple orders
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/orders', [
                'customer_id' => $customer->id,
                'items' => [
                    ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50.00],
                ],
            ]);
        }

        // Get customer details
        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(3, $data['total_orders']);
        $this->assertEquals(300.00, $data['total_spent']); // 3 orders * 100.00

        // Add payments to orders
        $orders = Order::where('customer_id', $customer->id)->get();
        foreach ($orders as $order) {
            $this->postJson("/api/orders/{$order->id}/payments", [
                'amount' => 50.00,
                'payment_method' => 'cash',
            ]);
        }

        // Verify customer payment statistics
        $response = $this->getJson("/api/customers/{$customer->id}");
        $data = $response->json('data');

        $this->assertEquals(150.00, $data['total_paid']); // 3 orders * 50.00
    }

    /**
     * Test discount calculation consistency
     */
    public function test_discount_calculation_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 100.00]);

        // Test percentage discount
        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ]);

        $order = Order::first();
        $this->assertEquals('100.00', $order->subtotal);
        $this->assertEquals('80.00', $order->total_amount); // 100 - 20%
        $this->assertEquals('percentage', $order->discount_type);
        $this->assertEquals('20.00', $order->discount_value);

        // Test fixed discount
        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'discount_type' => 'fixed',
            'discount_value' => 25.00,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ]);

        $order = Order::latest()->first();
        $this->assertEquals('100.00', $order->subtotal);
        $this->assertEquals('75.00', $order->total_amount); // 100 - 25
        $this->assertEquals('fixed', $order->discount_type);
        $this->assertEquals('25.00', $order->discount_value);
    }

    /**
     * Test cascade deletion consistency
     */
    public function test_cascade_deletion_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50.00],
            ],
        ]);

        $order = Order::first();

        // Add payment
        $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 50.00,
            'payment_method' => 'cash',
        ]);

        $this->assertCount(1, $order->orderItems);
        $this->assertCount(1, $order->payments);

        // Delete order
        $response = $this->deleteJson("/api/orders/{$order->id}");
        $response->assertStatus(200);

        // Verify related records are deleted
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);

        // Customer and item should still exist
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    /**
     * Test soft delete consistency
     */
    public function test_soft_delete_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create();

        // Create order with customer and item
        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 50.00],
            ],
        ]);

        $order = Order::first();

        // Soft delete customer
        $response = $this->deleteJson("/api/customers/{$customer->id}");

        // Customer should be soft deleted
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        // Order should still be accessible
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $customer->id]);

        // Soft delete item
        $response = $this->deleteJson("/api/items/{$item->id}");

        // Item should be soft deleted
        $this->assertSoftDeleted('items', ['id' => $item->id]);

        // Order items should still reference the item
        $this->assertDatabaseHas('order_items', ['item_id' => $item->id]);
    }

    /**
     * Test concurrent order updates
     */
    public function test_concurrent_order_updates_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 50.00],
            ],
        ]);

        $order = Order::first();

        // Simulate concurrent updates
        $response1 = $this->putJson("/api/orders/{$order->id}", [
            'status' => 'processed',
        ]);

        $response2 = $this->putJson("/api/orders/{$order->id}", [
            'notes' => 'Updated notes',
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $order->refresh();

        // Both updates should be applied
        $this->assertEquals('processed', $order->status);
        $this->assertEquals('Updated notes', $order->notes);
    }

    /**
     * Test order item price consistency
     */
    public function test_order_item_price_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        // Create order
        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 3, 'unit_price' => 50.00],
            ],
        ]);

        $order = Order::first();
        $orderItem = $order->orderItems->first();

        // Verify order item calculations
        $this->assertEquals('50.00', $orderItem->unit_price);
        $this->assertEquals(3, $orderItem->quantity);
        $this->assertEquals('150.00', $orderItem->total_price);

        // Update item price in catalog
        $item->update(['price' => 60.00]);

        // Order item price should remain unchanged (historical price)
        $orderItem->refresh();
        $this->assertEquals('50.00', $orderItem->unit_price);
        $this->assertEquals('150.00', $orderItem->total_price);
    }

    /**
     * Test payment amount validation consistency
     */
    public function test_payment_amount_validation_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 100.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ]);

        $order = Order::first();

        // Try to add negative payment
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => -50.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertEquals('unpaid', $order->payment_status);

        // Try to add zero payment
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 0,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);

        // Valid payment should work
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 50.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);
    }

    /**
     * Test order number uniqueness consistency
     */
    public function test_order_number_uniqueness_consistency()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        $orderNumbers = [];

        // Create multiple orders
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/orders', [
                'customer_id' => $customer->id,
                'items' => [
                    ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 50.00],
                ],
            ]);

            $response->assertStatus(201);
            $orderNumber = $response->json('data.order_number');
            $orderNumbers[] = $orderNumber;
        }

        // Verify all order numbers are unique
        $uniqueOrderNumbers = array_unique($orderNumbers);
        $this->assertCount(10, $uniqueOrderNumbers);
    }

    /**
     * Test data integrity after multiple operations
     */
    public function test_data_integrity_after_complex_operations()
    {
        $customer = Customer::factory()->create();
        $item1 = Item::factory()->create(['price' => 25.00, 'stock_quantity' => 100]);
        $item2 = Item::factory()->create(['price' => 15.00, 'stock_quantity' => 50]);

        // Create order
        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'items' => [
                ['item_id' => $item1->id, 'quantity' => 2, 'unit_price' => 25.00],
                ['item_id' => $item2->id, 'quantity' => 3, 'unit_price' => 15.00],
            ],
        ]);

        $order = Order::first();

        // Add multiple payments
        $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 40.00,
            'payment_method' => 'cash',
        ]);

        $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 45.50,
            'payment_method' => 'transfer',
        ]);

        // Update order status
        $this->putJson("/api/orders/{$order->id}", [
            'status' => 'completed',
        ]);

        // Verify final state
        $order->refresh();

        $this->assertEquals('completed', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('95.00', $order->subtotal);
        $this->assertEquals('85.50', $order->total_amount); // 95 - 10%
        $this->assertEquals(85.50, $order->total_paid);
        $this->assertEquals(0.00, $order->remaining_balance);
        $this->assertCount(2, $order->orderItems);
        $this->assertCount(2, $order->payments);
        $this->assertTrue($order->is_fully_paid);
    }
}
