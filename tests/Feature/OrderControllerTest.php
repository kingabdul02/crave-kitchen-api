<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Item $item1;
    protected Item $item2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->item1 = Item::factory()->create([
            'name' => 'Test Item 1',
            'price' => 10.00,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $this->item2 = Item::factory()->create([
            'name' => 'Test Item 2',
            'price' => 20.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
    }

    public function test_can_list_orders()
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'customer',
                        'status',
                        'total_amount',
                        'payment_status',
                        'created_at'
                    ]
                ],
                'meta' => [
                    'pagination'
                ]
            ]);
    }

    public function test_can_create_order()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 2,
                    'unit_price' => 10.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 1,
                    'unit_price' => 20.00,
                ]
            ],
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'notes' => 'Test order notes'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'customer',
                    'order_items',
                    'status',
                    'subtotal',
                    'discount_type',
                    'discount_value',
                    'total_amount',
                    'payment_status',
                    'notes'
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'subtotal' => 40.00, // (2 * 10) + (1 * 20)
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'total_amount' => 36.00, // 40 - (40 * 0.1)
            'payment_status' => 'unpaid',
            'notes' => 'Test order notes'
        ]);

        $this->assertDatabaseHas('order_items', [
            'item_id' => $this->item1->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'total_price' => 20.00
        ]);

        // Check stock was decremented
        $this->item1->refresh();
        $this->item2->refresh();
        $this->assertEquals(98, $this->item1->stock_quantity);
        $this->assertEquals(49, $this->item2->stock_quantity);
    }

    public function test_cannot_create_order_with_insufficient_stock()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 200, // More than available stock (100)
                    'unit_price' => 10.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message'
            ]);

        $this->assertDatabaseMissing('orders', [
            'customer_id' => $this->customer->id
        ]);
    }

    public function test_can_show_order()
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $this->item1->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'total_price' => 20.00
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/admin/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'customer',
                    'order_items' => [
                        '*' => [
                            'id',
                            'item',
                            'quantity',
                            'unit_price',
                            'total_price'
                        ]
                    ],
                    'payments',
                    'status',
                    'total_amount',
                    'payment_status'
                ]
            ]);
    }

    public function test_can_update_order()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'subtotal' => 20.00,
            'total_amount' => 20.00
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $this->item1->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'total_price' => 20.00
        ]);

        $updateData = [
            'status' => 'processed',
            'notes' => 'Updated notes'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/orders/{$order->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processed',
            'notes' => 'Updated notes'
        ]);
    }

    public function test_can_delete_order()
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $this->item1->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'total_price' => 20.00
        ]);

        // Decrease stock to simulate order creation
        $this->item1->decrement('stock_quantity', 2);
        $originalStock = $this->item1->stock_quantity;

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/admin/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order deleted successfully'
            ]);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);

        // Check stock was restored
        $this->item1->refresh();
        $this->assertEquals($originalStock + 2, $this->item1->stock_quantity);
    }

    public function test_can_update_order_status()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'completed'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order status updated successfully'
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed'
        ]);
    }

    public function test_can_update_payment_status()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_status' => 'unpaid'
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/orders/{$order->id}/payment-status", [
                'payment_status' => 'paid'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment status updated successfully'
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid'
        ]);
    }

    public function test_can_get_order_summary()
    {
        // Create test orders with different statuses
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'total_amount' => 100.00
        ]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'total_amount' => 200.00
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/orders-summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_orders',
                    'pending_orders',
                    'processed_orders',
                    'completed_orders',
                    'cancelled_orders',
                    'total_revenue',
                    'unpaid_amount'
                ]
            ]);
    }

    public function test_can_filter_orders_by_status()
    {
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending'
        ]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'completed'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/orders?status=pending');

        $response->assertStatus(200);

        $orders = $response->json('data');
        $this->assertCount(1, $orders);
        $this->assertEquals('pending', $orders[0]['status']);
    }

    public function test_can_search_orders()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD20241001'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/orders?search=ORD20241001');

        $response->assertStatus(200);

        $orders = $response->json('data');
        $this->assertCount(1, $orders);
        $this->assertEquals($order->order_number, $orders[0]['order_number']);
    }

    public function test_order_validation_rules()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'items']);
    }

    public function test_fixed_discount_calculation()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 2,
                    'unit_price' => 10.00,
                ]
            ],
            'discount_type' => 'fixed',
            'discount_value' => 5.00
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'subtotal' => 20.00,
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'total_amount' => 15.00 // 20 - 5
        ]);
    }

    public function test_fixed_discount_cannot_exceed_subtotal()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ]
            ],
            'discount_type' => 'fixed',
            'discount_value' => 15.00 // More than subtotal
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'subtotal' => 10.00,
            'discount_type' => 'fixed',
            'discount_value' => 15.00,
            'total_amount' => 0.00 // Discount capped at subtotal
        ]);
    }

    public function test_order_number_generation()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(201);

        $order = Order::latest()->first();
        $this->assertNotNull($order->order_number);
        $this->assertStringStartsWith('ORD', $order->order_number);
        $this->assertGreaterThanOrEqual(11, strlen($order->order_number)); // ORD + YYYYMMDD + sequence
    }

    public function test_cannot_create_order_with_inactive_item()
    {
        $inactiveItem = Item::factory()->create([
            'name' => 'Inactive Item',
            'price' => 15.00,
            'stock_quantity' => 10,
            'is_active' => false,
        ]);

        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $inactiveItem->id,
                    'quantity' => 1,
                    'unit_price' => 15.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message'
            ]);

        $this->assertStringContainsString('not active', $response->json('message'));
    }

    public function test_can_update_order_items()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'subtotal' => 20.00,
            'discount_type' => null,
            'discount_value' => 0,
            'total_amount' => 20.00,
            'order_number' => 'ORD20240601001'
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $this->item1->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'total_price' => 20.00
        ]);

        // Decrease stock to simulate order creation
        $this->item1->decrement('stock_quantity', 2);
        $originalStock = $this->item1->stock_quantity;

        $updateData = [
            'items' => [
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 1,
                    'unit_price' => 20.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/orders/{$order->id}", $updateData);

        $response->assertStatus(200);

        // Check that old stock was restored and new stock was decremented
        $this->item1->refresh();
        $this->item2->refresh();
        $this->assertEquals($originalStock + 2, $this->item1->stock_quantity); // Restored
        $this->assertEquals(49, $this->item2->stock_quantity); // Decremented

        // Check order was updated
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'subtotal' => 20.00,
            'total_amount' => 20.00
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_id' => $this->item2->id,
            'quantity' => 1,
            'unit_price' => 20.00,
            'total_price' => 20.00
        ]);
    }

    public function test_can_apply_percentage_discount_over_100()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ]
            ],
            'discount_type' => 'percentage',
            'discount_value' => 150 // Over 100%
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount_value']);
    }

    public function test_can_filter_orders_by_payment_status()
    {
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_status' => 'unpaid',
            'order_number' => 'ORD20240201001'
        ]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_status' => 'paid',
            'order_number' => 'ORD20240201002'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/orders?payment_status=paid');

        $response->assertStatus(200);

        $orders = $response->json('data');
        $this->assertCount(1, $orders);
        $this->assertEquals('paid', $orders[0]['payment_status']);
    }

    public function test_can_filter_orders_by_date_range()
    {
        $oldOrder = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD20240101001',
            'created_at' => now()->subDays(10)
        ]);
        $newOrder = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD20240102001',
            'created_at' => now()
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/orders?date_from=' . now()->subDays(5)->format('Y-m-d'));

        $response->assertStatus(200);

        $orders = $response->json('data');
        $this->assertCount(1, $orders);
        $this->assertEquals($newOrder->id, $orders[0]['id']);
    }

    public function test_can_search_orders_by_customer_name()
    {
        $customer2 = Customer::factory()->create(['name' => 'John Doe']);

        $order1 = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'order_number' => 'ORD20240301001'
        ]);
        $order2 = Order::factory()->create([
            'customer_id' => $customer2->id,
            'order_number' => 'ORD20240301002'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/orders?search=John');

        $response->assertStatus(200);

        $orders = $response->json('data');
        $this->assertCount(1, $orders);
        $this->assertEquals($order2->id, $orders[0]['id']);
    }

    public function test_order_summary_calculations()
    {
        // Create orders with different statuses and amounts
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 100.00,
            'order_number' => 'ORD20240401001'
        ]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 200.00,
            'order_number' => 'ORD20240401002'
        ]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'total_amount' => 50.00,
            'order_number' => 'ORD20240401003'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/orders-summary');

        $response->assertStatus(200);

        $summary = $response->json('data');
        $this->assertEquals(3, $summary['total_orders']);
        $this->assertEquals(1, $summary['pending_orders']);
        $this->assertEquals(1, $summary['completed_orders']);
        $this->assertEquals(1, $summary['cancelled_orders']);
        $this->assertEquals(200.00, $summary['total_revenue']); // Only completed/processed orders
        $this->assertEquals(100.00, $summary['unpaid_amount']); // Only pending order (cancelled excluded)
    }

    public function test_bulk_status_update_validation()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'order_number' => 'ORD20240501001'
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'invalid_status'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_payment_status_update_validation()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_status' => 'unpaid',
            'order_number' => 'ORD20240501002'
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/orders/{$order->id}/payment-status", [
                'payment_status' => 'invalid_payment_status'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_status']);
    }

    public function test_order_with_zero_discount()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ]
            ],
            'discount_type' => 'percentage',
            'discount_value' => 0
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'subtotal' => 10.00,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'total_amount' => 10.00 // No discount applied
        ]);
    }

    public function test_order_with_null_discount()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => 10.00,
                ]
            ]
            // No discount fields provided
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'subtotal' => 10.00,
            'discount_type' => null,
            'discount_value' => 0,
            'total_amount' => 10.00
        ]);
    }

    public function test_order_with_multiple_items_complex_calculation()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 3,
                    'unit_price' => 10.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 2,
                    'unit_price' => 25.00,
                ]
            ],
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'notes' => 'Complex order with multiple items and percentage discount'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/orders', $orderData);

        $response->assertStatus(201);

        // Subtotal: (3 * 10) + (2 * 25) = 30 + 50 = 80
        // Discount: 80 * 0.15 = 12
        // Total: 80 - 12 = 68
        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'subtotal' => 80.00,
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'total_amount' => 68.00,
            'notes' => 'Complex order with multiple items and percentage discount'
        ]);

        // Check individual order items
        $order = Order::latest()->first();
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_id' => $this->item1->id,
            'quantity' => 3,
            'unit_price' => 10.00,
            'total_price' => 30.00
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_id' => $this->item2->id,
            'quantity' => 2,
            'unit_price' => 25.00,
            'total_price' => 50.00
        ]);

        // Check stock was properly decremented
        $this->item1->refresh();
        $this->item2->refresh();
        $this->assertEquals(97, $this->item1->stock_quantity); // 100 - 3
        $this->assertEquals(48, $this->item2->stock_quantity); // 50 - 2
    }
}
