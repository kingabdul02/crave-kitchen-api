<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;
    protected Customer $customer;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = new PaymentService();
        $this->customer = Customer::factory()->create();
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => 100.00,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_can_create_payment()
    {
        $paymentData = [
            'amount' => 50.00,
            'payment_method' => 'cash',
            'notes' => 'Test payment',
        ];

        $payment = $this->paymentService->createPayment($this->order, $paymentData);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals($this->order->id, $payment->order_id);
        $this->assertEquals(50.00, $payment->amount);
        $this->assertEquals('cash', $payment->payment_method);
        $this->assertEquals('Test payment', $payment->notes);
        $this->assertNotNull($payment->payment_date);

        // Check that order payment status was updated
        $this->order->refresh();
        $this->assertEquals('partially_paid', $this->order->payment_status);
    }

    public function test_can_create_payment_with_custom_date()
    {
        $customDate = now()->subDay();
        $paymentData = [
            'amount' => 25.00,
            'payment_method' => 'transfer',
            'payment_date' => $customDate->toDateTimeString(),
        ];

        $payment = $this->paymentService->createPayment($this->order, $paymentData);

        $this->assertEquals($customDate->format('Y-m-d H:i:s'), $payment->payment_date->format('Y-m-d H:i:s'));
    }

    public function test_can_update_payment()
    {
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 30.00,
            'payment_method' => 'cash',
        ]);

        $updateData = [
            'amount' => 40.00,
            'payment_method' => 'transfer',
            'notes' => 'Updated notes',
        ];

        $updatedPayment = $this->paymentService->updatePayment($payment, $updateData);

        $this->assertEquals(40.00, $updatedPayment->amount);
        $this->assertEquals('transfer', $updatedPayment->payment_method);
        $this->assertEquals('Updated notes', $updatedPayment->notes);
    }

    public function test_can_delete_payment()
    {
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 25.00,
        ]);

        $result = $this->paymentService->deletePayment($payment);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);

        // Check that order payment status was updated
        $this->order->refresh();
        $this->assertEquals('unpaid', $this->order->payment_status);
    }

    public function test_get_payment_summary_with_no_payments()
    {
        $summary = $this->paymentService->getPaymentSummary($this->order);

        $this->assertEquals(100.00, $summary['order_total']);
        $this->assertEquals(0, $summary['total_paid']);
        $this->assertEquals(100.00, $summary['remaining_amount']);
        $this->assertEquals(0, $summary['overpayment']);
        $this->assertEquals('unpaid', $summary['payment_status']);
        $this->assertEquals(0, $summary['payment_count']);
        $this->assertEmpty($summary['payments_by_method']);
        $this->assertNull($summary['latest_payment_date']);
        $this->assertNull($summary['first_payment_date']);
    }

    public function test_get_payment_summary_with_partial_payments()
    {
        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 30.00,
            'payment_method' => 'cash',
        ]);
        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 20.00,
            'payment_method' => 'transfer',
        ]);

        $summary = $this->paymentService->getPaymentSummary($this->order);

        $this->assertEquals(100.00, $summary['order_total']);
        $this->assertEquals(50.00, $summary['total_paid']);
        $this->assertEquals(50.00, $summary['remaining_amount']);
        $this->assertEquals(0, $summary['overpayment']);
        $this->assertEquals('partially_paid', $summary['payment_status']);
        $this->assertEquals(2, $summary['payment_count']);

        $this->assertArrayHasKey('cash', $summary['payments_by_method']);
        $this->assertArrayHasKey('transfer', $summary['payments_by_method']);
        $this->assertEquals(1, $summary['payments_by_method']['cash']['count']);
        $this->assertEquals(30.00, $summary['payments_by_method']['cash']['total']);
    }

    public function test_get_payment_summary_with_overpayment()
    {
        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 120.00, // More than order total
            'payment_method' => 'cash',
        ]);

        $summary = $this->paymentService->getPaymentSummary($this->order);

        $this->assertEquals(100.00, $summary['order_total']);
        $this->assertEquals(120.00, $summary['total_paid']);
        $this->assertEquals(0, $summary['remaining_amount']);
        $this->assertEquals(20.00, $summary['overpayment']);
        $this->assertEquals('paid', $summary['payment_status']);
    }

    public function test_calculate_payment_status()
    {
        // Test unpaid status
        $status = $this->paymentService->calculatePaymentStatus($this->order);
        $this->assertEquals('unpaid', $status);

        // Test partially paid status
        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 50.00,
        ]);
        $status = $this->paymentService->calculatePaymentStatus($this->order);
        $this->assertEquals('partially_paid', $status);

        // Test paid status
        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 50.00,
        ]);
        $status = $this->paymentService->calculatePaymentStatus($this->order);
        $this->assertEquals('paid', $status);

        // Test overpaid status (still shows as paid)
        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 10.00,
        ]);
        $status = $this->paymentService->calculatePaymentStatus($this->order);
        $this->assertEquals('paid', $status);
    }

    public function test_validates_payment_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero.');

        $this->paymentService->createPayment($this->order, [
            'amount' => 0,
            'payment_method' => 'cash',
        ]);
    }

    public function test_validates_negative_payment_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero.');

        $this->paymentService->createPayment($this->order, [
            'amount' => -10.00,
            'payment_method' => 'cash',
        ]);
    }

    public function test_get_payment_statistics()
    {
        // Create payments across different methods and dates
        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 50.00,
            'payment_method' => 'cash',
            'payment_date' => now()->subDays(2),
        ]);

        $otherOrder = Order::factory()->create();
        Payment::factory()->create([
            'order_id' => $otherOrder->id,
            'amount' => 75.00,
            'payment_method' => 'transfer',
            'payment_date' => now()->subDay(),
        ]);

        $stats = $this->paymentService->getPaymentStatistics();

        $this->assertEquals(2, $stats['total_payments']);
        $this->assertEquals(125.00, $stats['total_amount']);
        $this->assertEquals(62.50, $stats['average_payment']);

        $this->assertArrayHasKey('cash', $stats['payments_by_method']);
        $this->assertArrayHasKey('transfer', $stats['payments_by_method']);
        $this->assertEquals(1, $stats['payments_by_method']['cash']['count']);
        $this->assertEquals(50.00, $stats['payments_by_method']['cash']['total']);
    }

    public function test_get_payment_statistics_with_filters()
    {
        // Create payments with different dates and methods
        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 50.00,
            'payment_method' => 'cash',
            'payment_date' => now()->subDays(5),
        ]);

        Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 30.00,
            'payment_method' => 'cash',
            'payment_date' => now()->subDay(),
        ]);

        // Filter by date range
        $stats = $this->paymentService->getPaymentStatistics([
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->assertEquals(1, $stats['total_payments']);
        $this->assertEquals(30.00, $stats['total_amount']);

        // Filter by payment method
        $stats = $this->paymentService->getPaymentStatistics([
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(2, $stats['total_payments']);
        $this->assertEquals(80.00, $stats['total_amount']);
    }
}
