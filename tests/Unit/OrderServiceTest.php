<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orderService;
    protected Customer $customer;
    protected Item $item1;
    protected Item $item2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = new OrderService();
        $this->customer = Customer::factory()->create();
        $this->item1 = Item::factory()->create(['price' => 25.00, 'stock_quantity' => 100]);
        $this->item2 = Item::factory()->create(['price' => 15.00, 'stock_quantity' => 50]);
    }

    public function test_can_create_order_with_items()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'notes' => 'Test order',
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

        $order = $this->orderService->createOrder($orderData);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($this->customer->id, $order->customer_id);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('Test order', $order->notes);
        $this->assertNotNull($order->order_number);

        // Check order items
        $this->assertCount(2, $order->orderItems);

        $orderItem1 = $order->orderItems->where('item_id', $this->item1->id)->first();
        $this->assertEquals(2, $orderItem1->quantity);
        $this->assertEquals('25.00', $orderItem1->unit_price);
        $this->assertEquals('50.00', $orderItem1->total_price);

        $orderItem2 = $order->orderItems->where('item_id', $this->item2->id)->first();
        $this->assertEquals(3, $orderItem2->quantity);
        $this->assertEquals('15.00', $orderItem2->unit_price);
        $this->assertEquals('45.00', $orderItem2->total_price);

        // Check calculated totals
        $this->assertEquals('95.00', $order->subtotal);
        $this->assertEquals('95.00', $order->total_amount);
    }

    public function test_can_create_order_with_discount()
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

        $order = $this->orderService->createOrder($orderData);

        $this->assertEquals('100.00', $order->subtotal);
        $this->assertEquals('percentage', $order->discount_type);
        $this->assertEquals('10.00', $order->discount_value);
        $this->assertEquals('90.00', $order->total_amount); // 100 - 10%
    }

    public function test_can_create_order_with_fixed_discount()
    {
        $orderData = [
            'customer_id' => $this->customer->id,
            'discount_type' => 'fixed',
            'discount_value' => 15.00,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 4,
                    'unit_price' => 25.00,
                ],
            ],
        ];

        $order = $this->orderService->createOrder($orderData);

        $this->assertEquals('100.00', $order->subtotal);
        $this->assertEquals('fixed', $order->discount_type);
        $this->assertEquals('15.00', $order->discount_value);
        $this->assertEquals('85.00', $order->total_amount); // 100 - 15
    }

    public function test_can_update_order()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        $updateData = [
            'status' => 'processed',
            'notes' => 'Updated notes',
        ];

        $updatedOrder = $this->orderService->updateOrder($order, $updateData);

        $this->assertEquals('processed', $updatedOrder->status);
        $this->assertEquals('Updated notes', $updatedOrder->notes);
    }

    public function test_can_update_order_items()
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);

        // Create initial order items
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $this->item1->id,
            'quantity' => 2,
            'unit_price' => 25.00,
        ]);

        $updateData = [
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 3, // Updated quantity
                    'unit_price' => 25.00,
                ],
                [
                    'item_id' => $this->item2->id, // New item
                    'quantity' => 1,
                    'unit_price' => 15.00,
                ],
            ],
        ];

        $updatedOrder = $this->orderService->updateOrder($order, $updateData);

        $this->assertCount(2, $updatedOrder->orderItems);

        // Check updated item
        $orderItem1 = $updatedOrder->orderItems->where('item_id', $this->item1->id)->first();
        $this->assertEquals(3, $orderItem1->quantity);

        // Check new item
        $orderItem2 = $updatedOrder->orderItems->where('item_id', $this->item2->id)->first();
        $this->assertEquals(1, $orderItem2->quantity);
    }

    public function test_can_delete_order()
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $this->item1->id,
        ]);

        $result = $this->orderService->deleteOrder($order);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
    }

    public function test_calculates_order_totals_correctly()
    {
        $items = [
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
        ];

        $totals = $this->orderService->calculateOrderTotals($items);

        $this->assertEquals(95.00, $totals['subtotal']);
        $this->assertEquals(95.00, $totals['total_amount']);
        $this->assertNull($totals['discount_type']);
        $this->assertNull($totals['discount_value']);
    }

    public function test_calculates_order_totals_with_percentage_discount()
    {
        $items = [
            [
                'item_id' => $this->item1->id,
                'quantity' => 4,
                'unit_price' => 25.00,
            ],
        ];

        $totals = $this->orderService->calculateOrderTotals($items, 'percentage', 15.00);

        $this->assertEquals(100.00, $totals['subtotal']);
        $this->assertEquals(85.00, $totals['total_amount']); // 100 - 15%
        $this->assertEquals('percentage', $totals['discount_type']);
        $this->assertEquals(15.00, $totals['discount_value']);
    }

    public function test_calculates_order_totals_with_fixed_discount()
    {
        $items = [
            [
                'item_id' => $this->item1->id,
                'quantity' => 4,
                'unit_price' => 25.00,
            ],
        ];

        $totals = $this->orderService->calculateOrderTotals($items, 'fixed', 20.00);

        $this->assertEquals(100.00, $totals['subtotal']);
        $this->assertEquals(80.00, $totals['total_amount']); // 100 - 20
        $this->assertEquals('fixed', $totals['discount_type']);
        $this->assertEquals(20.00, $totals['discount_value']);
    }

    public function test_prevents_negative_total_with_large_discount()
    {
        $items = [
            [
                'item_id' => $this->item1->id,
                'quantity' => 1,
                'unit_price' => 25.00,
            ],
        ];

        // Fixed discount larger than subtotal
        $totals = $this->orderService->calculateOrderTotals($items, 'fixed', 50.00);

        $this->assertEquals(25.00, $totals['subtotal']);
        $this->assertEquals(0.00, $totals['total_amount']); // Should not go negative
    }

    public function test_validates_order_data()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer ID is required.');

        $this->orderService->createOrder([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => 25.00,
                ],
            ],
        ]);
    }

    public function test_validates_order_items_required()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one item is required.');

        $this->orderService->createOrder([
            'customer_id' => $this->customer->id,
            'items' => [],
        ]);
    }

    public function test_validates_item_quantity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item quantity must be greater than zero.');

        $this->orderService->createOrder([
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 0,
                    'unit_price' => 25.00,
                ],
            ],
        ]);
    }

    public function test_validates_item_unit_price()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item unit price must be greater than zero.');

        $this->orderService->createOrder([
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 1,
                    'unit_price' => -5.00,
                ],
            ],
        ]);
    }

    public function test_can_get_order_summary()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'subtotal' => 100.00,
            'total_amount' => 90.00,
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $this->item1->id,
            'quantity' => 2,
            'unit_price' => 25.00,
            'total_price' => 50.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $this->item2->id,
            'quantity' => 3,
            'unit_price' => 15.00,
            'total_price' => 45.00,
        ]);

        $summary = $this->orderService->getOrderSummary($order);

        $this->assertEquals($order->id, $summary['order']['id']);
        $this->assertEquals($this->customer->name, $summary['customer']['name']);
        $this->assertCount(2, $summary['items']);
        $this->assertEquals(100.00, $summary['totals']['subtotal']);
        $this->assertEquals(90.00, $summary['totals']['total_amount']);
        $this->assertEquals(10.00, $summary['totals']['discount_amount']);
        $this->assertEquals(0, $summary['totals']['total_paid']);
        $this->assertEquals(90.00, $summary['totals']['remaining_balance']);
    }

    public function test_can_get_order_statistics()
    {
        // Create orders with different statuses
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'total_amount' => 100.00,
        ]);

        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'total_amount' => 150.00,
        ]);

        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'cancelled',
            'total_amount' => 75.00,
        ]);

        $stats = $this->orderService->getOrderStatistics();

        $this->assertEquals(3, $stats['total_orders']);
        $this->assertEquals(325.00, $stats['total_value']);
        $this->assertEquals(108.33, round($stats['average_order_value'], 2));

        $this->assertArrayHasKey('pending', $stats['orders_by_status']);
        $this->assertArrayHasKey('completed', $stats['orders_by_status']);
        $this->assertArrayHasKey('cancelled', $stats['orders_by_status']);

        $this->assertEquals(1, $stats['orders_by_status']['pending']['count']);
        $this->assertEquals(100.00, $stats['orders_by_status']['pending']['total_value']);
    }

    public function test_can_get_order_statistics_with_filters()
    {
        // Create orders with different dates
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'total_amount' => 100.00,
            'created_at' => now()->subDays(10),
        ]);

        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'total_amount' => 150.00,
            'created_at' => now()->subDay(),
        ]);

        // Filter by date range
        $stats = $this->orderService->getOrderStatistics([
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->assertEquals(1, $stats['total_orders']);
        $this->assertEquals(150.00, $stats['total_value']);

        // Filter by status
        $stats = $this->orderService->getOrderStatistics([
            'status' => 'completed',
        ]);

        $this->assertEquals(2, $stats['total_orders']);
        $this->assertEquals(250.00, $stats['total_value']);
    }
}
