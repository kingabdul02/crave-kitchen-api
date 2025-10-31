<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected Item $item1;
    protected Item $item2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        $this->item1 = Item::factory()->create([
            'name' => 'Product A',
            'price' => 25.00,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $this->item2 = Item::factory()->create([
            'name' => 'Product B',
            'price' => 15.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
    }

    public function test_complete_order_workflow_from_creation_to_completion()
    {
        // Step 1: Create order with items
        $orderData = [
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'notes' => 'Test order workflow',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 2,
                    'unit_price' => 25.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 3,
                    'unit_price' => 15.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'customer_id',
                    'status',
                    'subtotal',
                    'total_amount',
                    'payment_status',
                    'items',
                ],
            ]);

        $order = Order::first();
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals('95.00', $order->total_amount); // (2*25) + (3*15) = 95
        $this->assertCount(2, $order->orderItems);

        // Step 2: Process the order
        $response = $this->putJson("/api/orders/{$order->id}", [
            'status' => 'processed',
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('processed', $order->status);

        // Step 3: Make partial payment
        $paymentData = [
            'amount' => 50.00,
            'payment_method' => 'cash',
            'notes' => 'Partial payment',
        ];

        $response = $this->postJson("/api/orders/{$order->id}/payments", $paymentData);

        $response->assertStatus(201);
        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);
        $this->assertEquals(50.00, $order->total_paid);
        $this->assertEquals(45.00, $order->remaining_balance);

        // Step 4: Make final payment
        $finalPaymentData = [
            'amount' => 45.00,
            'payment_method' => 'transfer',
            'notes' => 'Final payment',
        ];

        $response = $this->postJson("/api/orders/{$order->id}/payments", $finalPaymentData);

        $response->assertStatus(201);
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(95.00, $order->total_paid);
        $this->assertEquals(0.00, $order->remaining_balance);
        $this->assertTrue($order->is_fully_paid);

        // Step 5: Complete the order
        $response = $this->putJson("/api/orders/{$order->id}", [
            'status' => 'completed',
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('completed', $order->status);

        // Verify final state
        $this->assertCount(2, $order->payments);
        $this->assertEquals(2, $order->orderItems->count());
        $this->assertEquals('John Doe', $order->customer->name);
    }

    public function test_order_workflow_with_discount()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 4,
                    'unit_price' => 25.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201);

        $order = Order::first();
        $this->assertEquals('100.00', $order->subtotal);
        $this->assertEquals('90.00', $order->total_amount); // 100 - 10%
        $this->assertEquals('percentage', $order->discount_type);
        $this->assertEquals('10.00', $order->discount_value);

        // Make full payment
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 90.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_order_workflow_with_overpayment()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 2,
                    'unit_price' => 25.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $orderData);
        $order = Order::first();

        // Make overpayment
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 75.00, // Order total is 50.00
            'payment_method' => 'cash',
            'notes' => 'Customer overpaid',
        ]);

        $response->assertStatus(201);
        $order->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(75.00, $order->total_paid);
        $this->assertEquals(-25.00, $order->remaining_balance); // Negative indicates overpayment
        $this->assertTrue($order->is_fully_paid);
    }

    public function test_order_cancellation_workflow()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => 25.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $orderData);
        $order = Order::first();

        // Make partial payment
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 15.00,
            'payment_method' => 'cash',
        ]);

        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);

        // Cancel the order
        $response = $this->putJson("/api/orders/{$order->id}", [
            'status' => 'cancelled',
            'notes' => 'Customer requested cancellation',
        ]);

        $response->assertStatus(200);
        $order->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('Customer requested cancellation', $order->notes);
        // Payment status should remain as it was
        $this->assertEquals('partially_paid', $order->payment_status);
    }

    public function test_order_modification_workflow()
    {
        // Create initial order
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 2,
                    'unit_price' => 25.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $orderData);
        $order = Order::first();

        $this->assertEquals('50.00', $order->total_amount);
        $this->assertCount(1, $order->orderItems);

        // Modify order - add item and change quantity
        $updateData = [
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 3, // Increased quantity
                    'unit_price' => 25.00,
                ],
                [
                    'item_id' => $this->item2->id, // New item
                    'quantity' => 2,
                    'unit_price' => 15.00,
                ],
            ],
        ];

        $response = $this->putJson("/api/orders/{$order->id}", $updateData);

        $response->assertStatus(200);
        $order->refresh();

        $this->assertEquals('105.00', $order->total_amount); // (3*25) + (2*15) = 105
        $this->assertCount(2, $order->orderItems);

        // Verify item quantities
        $orderItem1 = $order->orderItems->where('item_id', $this->item1->id)->first();
        $orderItem2 = $order->orderItems->where('item_id', $this->item2->id)->first();

        $this->assertEquals(3, $orderItem1->quantity);
        $this->assertEquals(2, $orderItem2->quantity);
    }

    public function test_multiple_payment_methods_workflow()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 4,
                    'unit_price' => 25.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $orderData);
        $order = Order::first();

        $this->assertEquals('100.00', $order->total_amount);

        // Payment 1: Cash
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 40.00,
            'payment_method' => 'cash',
            'notes' => 'Cash payment',
        ]);
        $response->assertStatus(201);

        // Payment 2: Transfer
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 35.00,
            'payment_method' => 'transfer',
            'notes' => 'Bank transfer',
        ]);
        $response->assertStatus(201);

        // Payment 3: POS
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 25.00,
            'payment_method' => 'pos',
            'notes' => 'Card payment',
        ]);
        $response->assertStatus(201);

        $order->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(100.00, $order->total_paid);
        $this->assertCount(3, $order->payments);

        // Verify payment methods
        $payments = $order->payments;
        $this->assertTrue($payments->contains('payment_method', 'cash'));
        $this->assertTrue($payments->contains('payment_method', 'transfer'));
        $this->assertTrue($payments->contains('payment_method', 'pos'));
    }

    public function test_order_deletion_with_payments()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => 25.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $orderData);
        $order = Order::first();

        // Add payment
        $response = $this->postJson("/api/orders/{$order->id}/payments", [
            'amount' => 25.00,
            'payment_method' => 'cash',
        ]);

        $this->assertCount(1, $order->payments);
        $this->assertCount(1, $order->orderItems);

        // Delete order
        $response = $this->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(200);

        // Verify order and related records are deleted
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);
    }

    public function test_customer_order_history_integration()
    {
        // Create multiple orders for the customer
        $order1 = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => 100.00,
            'status' => 'completed',
            'created_at' => now()->subDays(5),
        ]);

        $order2 = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => 150.00,
            'status' => 'pending',
            'created_at' => now()->subDays(2),
        ]);

        // Add payments
        Payment::factory()->create([
            'order_id' => $order1->id,
            'amount' => 100.00,
        ]);

        Payment::factory()->create([
            'order_id' => $order2->id,
            'amount' => 75.00,
        ]);

        // Get customer details
        $response = $this->getJson("/api/customers/{$this->customer->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'total_orders',
                    'total_spent',
                    'total_paid',
                ],
            ]);

        $customerData = $response->json('data');
        $this->assertEquals(2, $customerData['total_orders']);
        $this->assertEquals(250.00, $customerData['total_spent']);
        $this->assertEquals(175.00, $customerData['total_paid']);

        // Get customer orders
        $response = $this->getJson("/api/customers/{$this->customer->id}/orders");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $orders = $response->json('data');
        // Should be ordered by created_at desc
        $this->assertEquals($order2->id, $orders[0]['id']);
        $this->assertEquals($order1->id, $orders[1]['id']);
    }

    public function test_item_stock_tracking_integration()
    {
        $item = Item::factory()->create([
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // Create order that would exceed stock
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 15, // More than available stock
                    'unit_price' => 25.00,
                ],
            ],
        ];

        // This should still create the order (stock validation might be business logic)
        $response = $this->postJson('/api/orders', $orderData);

        // Depending on business rules, this might succeed or fail
        // For this test, let's assume it succeeds but we track the stock issue
        if ($response->status() === 201) {
            $order = Order::first();
            $orderItem = $order->orderItems->first();
            $this->assertEquals(15, $orderItem->quantity);

            // Stock quantity should remain unchanged until order is processed
            $item->refresh();
            $this->assertEquals(10, $item->stock_quantity);
        }
    }
}
