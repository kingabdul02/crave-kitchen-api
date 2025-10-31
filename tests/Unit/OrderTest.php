<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_be_created_with_valid_data()
    {
        $customer = Customer::factory()->create();

        $orderData = [
            'customer_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 100.00,
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'total_amount' => 90.00,
            'payment_status' => 'unpaid',
            'notes' => 'Test order notes',
        ];

        $order = Order::create($orderData);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('90.00', $order->total_amount);
        $this->assertEquals('percentage', $order->discount_type);
        $this->assertEquals('10.00', $order->discount_value);
        $this->assertNotNull($order->order_number);
    }

    public function test_order_generates_unique_order_number_on_creation()
    {
        $customer = Customer::factory()->create();

        $order1 = Order::factory()->create(['customer_id' => $customer->id]);
        $order2 = Order::factory()->create(['customer_id' => $customer->id]);

        $this->assertNotNull($order1->order_number);
        $this->assertNotNull($order2->order_number);
        $this->assertNotEquals($order1->order_number, $order2->order_number);

        // Order numbers should follow the pattern ORD{YYYYMMDD}{SEQUENCE}
        $this->assertMatchesRegularExpression('/^ORD\d{8}\d{4}$/', $order1->order_number);
        $this->assertMatchesRegularExpression('/^ORD\d{8}\d{4}$/', $order2->order_number);
    }

    public function test_order_belongs_to_customer()
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $this->assertInstanceOf(Customer::class, $order->customer);
        $this->assertEquals($customer->id, $order->customer->id);
    }

    public function test_order_has_many_order_items()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
        ]);

        $this->assertTrue($order->orderItems->contains($orderItem));
        $this->assertCount(1, $order->orderItems);
    }

    public function test_order_has_many_payments()
    {
        $order = Order::factory()->create();

        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->assertTrue($order->payments->contains($payment));
        $this->assertCount(1, $order->payments);
    }

    public function test_order_belongs_to_many_items_through_order_items()
    {
        $order = Order::factory()->create();
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item1->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'total_price' => 100.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item2->id,
            'quantity' => 1,
            'unit_price' => 30.00,
            'total_price' => 30.00,
        ]);

        $items = $order->items;
        $this->assertCount(2, $items);
        $this->assertTrue($items->contains($item1));
        $this->assertTrue($items->contains($item2));

        // Check pivot data
        $item1Pivot = $items->where('id', $item1->id)->first()->pivot;
        $this->assertEquals(2, $item1Pivot->quantity);
        $this->assertEquals('50.00', $item1Pivot->unit_price);
        $this->assertEquals('100.00', $item1Pivot->total_price);
    }

    public function test_order_total_paid_attribute()
    {
        $order = Order::factory()->create(['total_amount' => 100.00]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 30.00,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 20.00,
        ]);

        $this->assertEquals(50.00, $order->total_paid);
    }

    public function test_order_remaining_balance_attribute()
    {
        $order = Order::factory()->create(['total_amount' => 100.00]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 30.00,
        ]);

        $this->assertEquals(70.00, $order->remaining_balance);
    }

    public function test_order_is_fully_paid_attribute()
    {
        $order = Order::factory()->create(['total_amount' => 100.00]);

        // Not fully paid
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
        ]);
        $this->assertFalse($order->is_fully_paid);

        // Fully paid
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
        ]);
        $order->refresh();
        $this->assertTrue($order->is_fully_paid);

        // Overpaid
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 10.00,
        ]);
        $order->refresh();
        $this->assertTrue($order->is_fully_paid);
    }

    public function test_order_by_status_scope()
    {
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'completed']);
        Order::factory()->create(['status' => 'pending']);

        $pendingOrders = Order::byStatus('pending')->get();
        $completedOrders = Order::byStatus('completed')->get();

        $this->assertCount(2, $pendingOrders);
        $this->assertCount(1, $completedOrders);

        $pendingOrders->each(function ($order) {
            $this->assertEquals('pending', $order->status);
        });
    }

    public function test_order_by_payment_status_scope()
    {
        Order::factory()->create(['payment_status' => 'unpaid']);
        Order::factory()->create(['payment_status' => 'paid']);
        Order::factory()->create(['payment_status' => 'unpaid']);

        $unpaidOrders = Order::byPaymentStatus('unpaid')->get();
        $paidOrders = Order::byPaymentStatus('paid')->get();

        $this->assertCount(2, $unpaidOrders);
        $this->assertCount(1, $paidOrders);

        $unpaidOrders->each(function ($order) {
            $this->assertEquals('unpaid', $order->payment_status);
        });
    }

    public function test_order_generate_order_number_method()
    {
        $orderNumber = Order::generateOrderNumber();

        $this->assertIsString($orderNumber);
        $this->assertMatchesRegularExpression('/^ORD\d{8}\d{4}$/', $orderNumber);

        // Should contain today's date
        $today = now()->format('Ymd');
        $this->assertStringContains($today, $orderNumber);
    }

    public function test_order_number_sequence_increments_daily()
    {
        $customer = Customer::factory()->create();

        // Create first order of the day
        $order1 = Order::factory()->create(['customer_id' => $customer->id]);

        // Create second order of the day
        $order2 = Order::factory()->create(['customer_id' => $customer->id]);

        $today = now()->format('Ymd');
        $expectedPattern1 = 'ORD' . $today . '0001';
        $expectedPattern2 = 'ORD' . $today . '0002';

        $this->assertEquals($expectedPattern1, $order1->order_number);
        $this->assertEquals($expectedPattern2, $order2->order_number);
    }

    public function test_order_casts_decimal_fields_correctly()
    {
        $order = Order::factory()->create([
            'subtotal' => '99.99',
            'discount_value' => '10.50',
            'total_amount' => '89.49',
        ]);

        $this->assertIsString($order->subtotal);
        $this->assertIsString($order->discount_value);
        $this->assertIsString($order->total_amount);

        $this->assertEquals('99.99', $order->subtotal);
        $this->assertEquals('10.50', $order->discount_value);
        $this->assertEquals('89.49', $order->total_amount);
    }

    public function test_order_casts_datetime_fields_correctly()
    {
        $order = Order::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $order->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $order->updated_at);
    }

    public function test_order_fillable_attributes()
    {
        $customer = Customer::factory()->create();

        $orderData = [
            'customer_id' => $customer->id,
            'order_number' => 'CUSTOM001',
            'status' => 'processed',
            'subtotal' => 150.00,
            'discount_type' => 'fixed',
            'discount_value' => 25.00,
            'total_amount' => 125.00,
            'payment_status' => 'partially_paid',
            'notes' => 'Custom order notes',
        ];

        $order = Order::create($orderData);

        foreach ($orderData as $key => $value) {
            $this->assertEquals($value, $order->$key);
        }
    }

    public function test_order_status_enum_values()
    {
        $customer = Customer::factory()->create();

        $validStatuses = ['pending', 'processed', 'completed', 'cancelled'];

        foreach ($validStatuses as $status) {
            $order = Order::factory()->create([
                'customer_id' => $customer->id,
                'status' => $status,
            ]);

            $this->assertEquals($status, $order->status);
        }
    }

    public function test_order_payment_status_enum_values()
    {
        $customer = Customer::factory()->create();

        $validPaymentStatuses = ['unpaid', 'partially_paid', 'paid'];

        foreach ($validPaymentStatuses as $paymentStatus) {
            $order = Order::factory()->create([
                'customer_id' => $customer->id,
                'payment_status' => $paymentStatus,
            ]);

            $this->assertEquals($paymentStatus, $order->payment_status);
        }
    }

    public function test_order_discount_type_enum_values()
    {
        $customer = Customer::factory()->create();

        $validDiscountTypes = ['percentage', 'fixed'];

        foreach ($validDiscountTypes as $discountType) {
            $order = Order::factory()->create([
                'customer_id' => $customer->id,
                'discount_type' => $discountType,
                'discount_value' => 10.00,
            ]);

            $this->assertEquals($discountType, $order->discount_type);
        }
    }
}
