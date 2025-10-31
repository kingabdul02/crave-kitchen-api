<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'total_amount' => 100.00,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_can_list_payments_for_order()
    {
        // Create some payments for the order
        Payment::factory()->count(3)->create([
            'order_id' => $this->order->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/admin/orders/{$this->order->id}/payments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'order_id',
                        'amount',
                        'payment_method',
                        'payment_method_label',
                        'notes',
                        'payment_date',
                        'payment_date_formatted',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);

        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_can_create_payment()
    {
        $paymentData = [
            'amount' => 50.00,
            'payment_method' => 'cash',
            'notes' => 'Partial payment',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/admin/orders/{$this->order->id}/payments", $paymentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_id',
                    'amount',
                    'payment_method',
                    'notes',
                    'payment_date',
                ]
            ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'amount' => 50.00,
            'payment_method' => 'cash',
            'notes' => 'Partial payment',
        ]);

        // Check that order payment status was updated
        $this->order->refresh();
        $this->assertEquals('partially_paid', $this->order->payment_status);
    }

    public function test_can_create_payment_that_fully_pays_order()
    {
        $paymentData = [
            'amount' => 100.00,
            'payment_method' => 'transfer',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/admin/orders/{$this->order->id}/payments", $paymentData);

        $response->assertStatus(201);

        // Check that order payment status was updated to paid
        $this->order->refresh();
        $this->assertEquals('paid', $this->order->payment_status);
    }

    public function test_can_create_overpayment()
    {
        $paymentData = [
            'amount' => 150.00, // More than order total
            'payment_method' => 'cash',
            'notes' => 'Overpayment test',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/admin/orders/{$this->order->id}/payments", $paymentData);

        $response->assertStatus(201);

        // Check that order payment status was updated to paid
        $this->order->refresh();
        $this->assertEquals('paid', $this->order->payment_status);
    }

    public function test_can_show_specific_payment()
    {
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 25.00,
            'payment_method' => 'pos',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/admin/orders/{$this->order->id}/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'order_id' => $this->order->id,
                    'amount' => 25.00,
                    'payment_method' => 'pos',
                ]
            ]);
    }

    public function test_cannot_show_payment_from_different_order()
    {
        $otherOrder = Order::factory()->create();
        $payment = Payment::factory()->create([
            'order_id' => $otherOrder->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/admin/orders/{$this->order->id}/payments/{$payment->id}");

        $response->assertStatus(404);
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
            'notes' => 'Updated payment',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/orders/{$this->order->id}/payments/{$payment->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => 40.00,
            'payment_method' => 'transfer',
            'notes' => 'Updated payment',
        ]);
    }

    public function test_can_delete_payment()
    {
        $payment = Payment::factory()->create([
            'order_id' => $this->order->id,
            'amount' => 25.00,
        ]);

        // First, verify the payment exists and order status is updated
        $this->order->refresh();
        $this->assertEquals('partially_paid', $this->order->payment_status);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/admin/orders/{$this->order->id}/payments/{$payment->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('payments', [
            'id' => $payment->id,
        ]);

        // Check that order payment status was updated back to unpaid
        $this->order->refresh();
        $this->assertEquals('unpaid', $this->order->payment_status);
    }

    public function test_can_get_payment_summary()
    {
        // Create multiple payments
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

        $response = $this->actingAs($this->user)
            ->getJson("/api/admin/orders/{$this->order->id}/payments/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'order_total',
                    'total_paid',
                    'remaining_amount',
                    'overpayment',
                    'payment_status',
                    'payment_count',
                    'payments_by_method',
                    'latest_payment_date',
                    'first_payment_date',
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(100.00, $data['order_total']);
        $this->assertEquals(50.00, $data['total_paid']);
        $this->assertEquals(50.00, $data['remaining_amount']);
        $this->assertEquals(0, $data['overpayment']);
        $this->assertEquals(2, $data['payment_count']);
    }

    public function test_payment_validation_rules()
    {
        // Test required fields
        $response = $this->actingAs($this->user)
            ->postJson("/api/admin/orders/{$this->order->id}/payments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'payment_method']);

        // Test invalid amount
        $response = $this->actingAs($this->user)
            ->postJson("/api/admin/orders/{$this->order->id}/payments", [
                'amount' => -10,
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Test invalid payment method
        $response = $this->actingAs($this->user)
            ->postJson("/api/admin/orders/{$this->order->id}/payments", [
                'amount' => 50.00,
                'payment_method' => 'invalid_method',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        // Test future payment date
        $response = $this->actingAs($this->user)
            ->postJson("/api/admin/orders/{$this->order->id}/payments", [
                'amount' => 50.00,
                'payment_method' => 'cash',
                'payment_date' => now()->addDay()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_date']);
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson("/api/admin/orders/{$this->order->id}/payments");
        $response->assertStatus(401);

        $response = $this->postJson("/api/admin/orders/{$this->order->id}/payments", [
            'amount' => 50.00,
            'payment_method' => 'cash',
        ]);
        $response->assertStatus(401);
    }
}
