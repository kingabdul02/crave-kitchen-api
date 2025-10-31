<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test order with zero discount
     */
    public function test_order_with_zero_discount()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 100.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ]);

        $response->assertStatus(201);
        $order = Order::first();

        $this->assertEquals('100.00', $order->subtotal);
        $this->assertEquals('100.00', $order->total_amount);
    }

    /**
     * Test order with 100% discount
     */
    public function test_order_with_full_discount()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 100.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'discount_type' => 'percentage',
            'discount_value' => 100.00,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
        ]);

        $response->assertStatus(201);
        $order = Order::first();

        $this->assertEquals('100.00', $order->subtotal);
        $this->assertEquals('0.00', $order->total_amount);
    }

    /**
     * Test order with very large quantity
     */
    public function test_order_with_large_quantity()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 10.00, 'stock_quantity' => 10000]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 9999, 'unit_price' => 10.00],
            ],
        ]);

        $response->assertStatus(201);
        $order = Order::first();

        $this->assertEquals('99990.00', $order->total_amount);
    }

    /**
     * Test order with very small price (cents)
     */
    public function test_order_with_small_price()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 0.01]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 100, 'unit_price' => 0.01],
            ],
        ]);

        $response->assertStatus(201);
        $order = Order::first();

        $this->assertEquals('1.00', $order->total_amount);
    }

    /**
     * Test order with decimal quantities (should be rejected or rounded)
     */
    public function test_order_with_decimal_quantity()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 10.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 2.5, 'unit_price' => 10.00],
            ],
        ]);

        // Should either reject or round the quantity
        if ($response->status() === 422) {
            $response->assertStatus(422);
        } else {
            $response->assertStatus(201);
            $order = Order::first();
            $orderItem = $order->orderItems->first();
            $this->assertIsInt($orderItem->quantity);
        }
    }

    /**
     * Test payment with very small amount
     */
    public function test_payment_with_small_amount()
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

        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 0.01,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $order->refresh();

        $this->assertEquals(0.01, $order->total_paid);
        $this->assertEquals('partially_paid', $order->payment_status);
    }

    /**
     * Test order with empty notes
     */
    public function test_order_with_empty_notes()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'notes' => '',
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 50.00],
            ],
        ]);

        $response->assertStatus(201);
        $order = Order::first();

        $this->assertEmpty($order->notes);
    }

    /**
     * Test order with very long notes
     */
    public function test_order_with_long_notes()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        $longNotes = str_repeat('This is a very long note. ', 100);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'notes' => $longNotes,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 50.00],
            ],
        ]);

        $response->assertStatus(201);
        $order = Order::first();

        $this->assertNotEmpty($order->notes);
    }

    /**
     * Test customer with special characters in name
     */
    public function test_customer_with_special_characters()
    {
        $response = $this->postJson('/api/customers', [
            'name' => "O'Brien & Sons (Pty) Ltd.",
            'email' => 'obrien@example.com',
            'phone' => '+1-234-567-8900',
        ]);

        $response->assertStatus(201);
        $customer = Customer::first();

        $this->assertEquals("O'Brien & Sons (Pty) Ltd.", $customer->name);
        $this->assertEquals('+1-234-567-8900', $customer->phone);
    }

    /**
     * Test item with zero stock
     */
    public function test_item_with_zero_stock()
    {
        $response = $this->postJson('/api/items', [
            'name' => 'Out of Stock Item',
            'price' => 50.00,
            'category' => 'Test',
            'stock_quantity' => 0,
        ]);

        $response->assertStatus(201);
        $item = Item::first();

        $this->assertEquals(0, $item->stock_quantity);
        $this->assertTrue($item->is_active);
    }

    /**
     * Test order with out of stock item
     */
    public function test_order_with_out_of_stock_item()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00, 'stock_quantity' => 0]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 50.00],
            ],
        ]);

        // Should either warn or allow (depending on business rules)
        // For now, we'll accept it creates the order
        $response->assertStatus(201);
    }

    /**
     * Test order status transitions
     */
    public function test_order_status_transitions()
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
        $this->assertEquals('pending', $order->status);

        // Transition to processed
        $this->putJson("/api/orders/{$order->id}", ['status' => 'processed']);
        $order->refresh();
        $this->assertEquals('processed', $order->status);

        // Transition to completed
        $this->putJson("/api/orders/{$order->id}", ['status' => 'completed']);
        $order->refresh();
        $this->assertEquals('completed', $order->status);

        // Try to transition back to pending (should be allowed or rejected based on business rules)
        $response = $this->putJson("/api/orders/{$order->id}", ['status' => 'pending']);
        // Accept either outcome
        $this->assertContains($response->status(), [200, 422]);
    }

    /**
     * Test order cancellation with payments
     */
    public function test_order_cancellation_with_payments()
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

        // Add payment
        $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 50.00,
            'payment_method' => 'cash',
        ]);

        // Cancel order
        $response = $this->putJson("/api/orders/{$order->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200);
        $order->refresh();

        $this->assertEquals('cancelled', $order->status);
        // Payment status should remain
        $this->assertEquals('partially_paid', $order->payment_status);
        $this->assertEquals(50.00, $order->total_paid);
    }

    /**
     * Test multiple orders for same customer simultaneously
     */
    public function test_multiple_simultaneous_orders_for_customer()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        $orders = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/orders', [
                'customer_id' => $customer->id,
                'items' => [
                    ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 50.00],
                ],
            ]);

            $response->assertStatus(201);
            $orders[] = $response->json('data.id');
        }

        // Verify all orders were created
        $this->assertCount(5, array_unique($orders));
        $this->assertEquals(5, Order::where('customer_id', $customer->id)->count());
    }

    /**
     * Test customer with no email or phone
     */
    public function test_customer_with_minimal_information()
    {
        $response = $this->postJson('/api/customers', [
            'name' => 'John Doe',
        ]);

        $response->assertStatus(201);
        $customer = Customer::first();

        $this->assertEquals('John Doe', $customer->name);
        $this->assertNull($customer->email);
        $this->assertNull($customer->phone);
    }

    /**
     * Test item category with special characters
     */
    public function test_item_category_with_special_characters()
    {
        $response = $this->postJson('/api/items', [
            'name' => 'Test Item',
            'price' => 50.00,
            'category' => 'Electronics & Gadgets (New)',
            'stock_quantity' => 10,
        ]);

        $response->assertStatus(201);
        $item = Item::first();

        $this->assertEquals('Electronics & Gadgets (New)', $item->category);
    }

    /**
     * Test order with discount greater than subtotal
     */
    public function test_order_with_excessive_fixed_discount()
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['price' => 50.00]);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'discount_type' => 'fixed',
            'discount_value' => 100.00, // Greater than order total
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 50.00],
            ],
        ]);

        // Should either reject or set total to 0
        if ($response->status() === 422) {
            $response->assertStatus(422);
        } else {
            $response->assertStatus(201);
            $order = Order::first();
            $this->assertGreaterThanOrEqual(0, $order->total_amount);
        }
    }

    /**
     * Test pagination with no results
     */
    public function test_pagination_with_no_results()
    {
        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    /**
     * Test search with special characters
     */
    public function test_search_with_special_characters()
    {
        $customer = Customer::factory()->create(['name' => "O'Brien"]);

        $response = $this->getJson('/api/customers?search=' . urlencode("O'Brien"));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
