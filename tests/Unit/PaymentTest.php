<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_can_be_created_with_valid_data()
    {
        $order = Order::factory()->create(['total_amount' => 100.00]);

        $paymentData = [
            'order_id' => $order->id,
            'amount' => 50.00,
            'payment_method' => 'cash',
            'notes' => 'Test payment notes',
            'payment_date' => now(),
        ];

        $payment = Payment::create($paymentData);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals($order->id, $payment->order_id);
        $this->assertEquals('50.00', $payment->amount);
        $this->assertEquals('cash', $payment->payment_method);
        $this->assertEquals('Test payment notes', $payment->notes);
        $this->assertNotNull($payment->payment_date);
    }

    public function test_payment_belongs_to_order()
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(Order::class, $payment->order);
        $this->assertEquals($order->id, $payment->order->id);
    }

    public function test_payment_updates_order_payment_status_on_creation()
    {
        $order = Order::factory()->create([
            'total_amount' => 100.00,
            'payment_status' => 'unpaid',
        ]);

        // Create partial payment
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
        ]);

        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);

        // Create full payment
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
        ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_payment_updates_order_payment_status_on_update()
    {
        $order = Order::factory()->create([
            'total_amount' => 100.00,
            'payment_status' => 'unpaid',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 30.00,
        ]);

        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);

        // Update payment amount to full payment
        $payment->update(['amount' => 100.00]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_payment_updates_order_payment_status_on_deletion()
    {
        $order = Order::factory()->create([
            'total_amount' => 100.00,
            'payment_status' => 'unpaid',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 100.00,
        ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        // Delete payment
        $payment->delete();

        $order->refresh();
        $this->assertEquals('unpaid', $order->payment_status);
    }

    public function test_payment_update_order_payment_status_method()
    {
        $order = Order::factory()->create([
            'total_amount' => 100.00,
            'payment_status' => 'unpaid',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 25.00,
        ]);

        // Test unpaid status (no payments)
        $order->payments()->delete();
        $payment->updateOrderPaymentStatus();
        $order->refresh();
        $this->assertEquals('unpaid', $order->payment_status);

        // Test partially paid status
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
        ]);
        $payment->updateOrderPaymentStatus();
        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);

        // Test paid status
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
        ]);
        $payment->updateOrderPaymentStatus();
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        // Test overpaid status (still shows as paid)
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 10.00,
        ]);
        $payment->updateOrderPaymentStatus();
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_payment_by_payment_method_scope()
    {
        $order = Order::factory()->create();

        Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => 'transfer',
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
        ]);

        $cashPayments = Payment::byPaymentMethod('cash')->get();
        $transferPayments = Payment::byPaymentMethod('transfer')->get();

        $this->assertCount(2, $cashPayments);
        $this->assertCount(1, $transferPayments);

        $cashPayments->each(function ($payment) {
            $this->assertEquals('cash', $payment->payment_method);
        });
    }

    public function test_payment_casts_amount_to_decimal()
    {
        $order = Order::factory()->create();

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '99.99',
        ]);

        $this->assertIsString($payment->amount);
        $this->assertEquals('99.99', $payment->amount);
    }

    public function test_payment_casts_payment_date_to_datetime()
    {
        $order = Order::factory()->create();

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'payment_date' => '2023-12-25 10:30:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->payment_date);
        $this->assertEquals('2023-12-25 10:30:00', $payment->payment_date->format('Y-m-d H:i:s'));
    }

    public function test_payment_casts_timestamps_to_datetime()
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->updated_at);
    }

    public function test_payment_fillable_attributes()
    {
        $order = Order::factory()->create();

        $paymentData = [
            'order_id' => $order->id,
            'amount' => 75.50,
            'payment_method' => 'pos',
            'notes' => 'POS payment with receipt #12345',
            'payment_date' => now()->subHour(),
        ];

        $payment = Payment::create($paymentData);

        $this->assertEquals($order->id, $payment->order_id);
        $this->assertEquals('75.50', $payment->amount);
        $this->assertEquals('pos', $payment->payment_method);
        $this->assertEquals('POS payment with receipt #12345', $payment->notes);
        $this->assertNotNull($payment->payment_date);
    }

    public function test_payment_method_enum_values()
    {
        $order = Order::factory()->create();

        $validPaymentMethods = ['cash', 'transfer', 'pos', 'other'];

        foreach ($validPaymentMethods as $method) {
            $payment = Payment::factory()->create([
                'order_id' => $order->id,
                'payment_method' => $method,
            ]);

            $this->assertEquals($method, $payment->payment_method);
        }
    }

    public function test_payment_handles_zero_amount()
    {
        $order = Order::factory()->create(['total_amount' => 100.00]);

        // This might be used for recording failed payments or refunds
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 0.00,
        ]);

        $this->assertEquals('0.00', $payment->amount);

        // Order should remain unpaid
        $order->refresh();
        $this->assertEquals('unpaid', $order->payment_status);
    }

    public function test_payment_handles_large_amounts()
    {
        $order = Order::factory()->create(['total_amount' => 999999.99]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 999999.99,
        ]);

        $this->assertEquals('999999.99', $payment->amount);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_payment_handles_overpayment()
    {
        $order = Order::factory()->create(['total_amount' => 100.00]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 150.00, // Overpayment
        ]);

        $this->assertEquals('150.00', $payment->amount);

        // Order should be marked as paid even with overpayment
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_multiple_payments_for_same_order()
    {
        $order = Order::factory()->create(['total_amount' => 100.00]);

        $payment1 = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 30.00,
            'payment_method' => 'cash',
        ]);

        $payment2 = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 40.00,
            'payment_method' => 'transfer',
        ]);

        $payment3 = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 30.00,
            'payment_method' => 'pos',
        ]);

        $payments = $order->payments;
        $this->assertCount(3, $payments);
        $this->assertTrue($payments->contains($payment1));
        $this->assertTrue($payments->contains($payment2));
        $this->assertTrue($payments->contains($payment3));

        // Total should be 100.00 and order should be paid
        $this->assertEquals(100.00, $order->total_paid);
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    public function test_payment_can_have_notes()
    {
        $order = Order::factory()->create();

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'notes' => 'Customer paid with exact change. Receipt #ABC123.',
        ]);

        $this->assertEquals('Customer paid with exact change. Receipt #ABC123.', $payment->notes);
    }

    public function test_payment_can_have_null_notes()
    {
        $order = Order::factory()->create();

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'notes' => null,
        ]);

        $this->assertNull($payment->notes);
    }

    public function test_payment_defaults_payment_date_if_not_provided()
    {
        $order = Order::factory()->create();

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            // Not providing payment_date
        ]);

        $this->assertNotNull($payment->payment_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->payment_date);
    }
}
