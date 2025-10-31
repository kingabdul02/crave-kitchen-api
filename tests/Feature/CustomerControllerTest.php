<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate a user for all tests
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    public function test_can_list_customers()
    {
        Customer::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'address',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    public function test_can_search_customers()
    {
        Customer::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        Customer::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->getJson('/api/admin/customers?search=John');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('John Doe', $response->json('data.0.name'));
    }

    public function test_can_filter_customers_with_orders()
    {
        $customerWithOrders = Customer::factory()->create();
        $customerWithoutOrders = Customer::factory()->create();

        Order::factory()->create(['customer_id' => $customerWithOrders->id]);

        $response = $this->getJson('/api/admin/customers?has_orders=true');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals($customerWithOrders->id, $response->json('data.0.id'));
    }

    public function test_can_create_customer()
    {
        $customerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St'
        ];

        $response = $this->postJson('/api/admin/customers', $customerData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'address',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('customers', $customerData);
    }

    public function test_can_create_customer_with_only_name()
    {
        $customerData = [
            'name' => 'Jane Doe'
        ];

        $response = $this->postJson('/api/admin/customers', $customerData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', $customerData);
    }

    public function test_cannot_create_customer_without_name()
    {
        $response = $this->postJson('/api/admin/customers', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_create_customer_with_duplicate_email()
    {
        Customer::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/admin/customers', [
            'name' => 'John Doe',
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_show_customer()
    {
        $customer = Customer::factory()->create();

        $response = $this->getJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'address',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    public function test_can_update_customer()
    {
        $customer = Customer::factory()->create();
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '9876543210',
            'address' => '456 Updated St'
        ];

        $response = $this->putJson("/api/admin/customers/{$customer->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', array_merge(['id' => $customer->id], $updateData));
    }

    public function test_cannot_update_customer_with_duplicate_email()
    {
        $customer1 = Customer::factory()->create(['email' => 'existing@example.com']);
        $customer2 = Customer::factory()->create(['email' => 'other@example.com']);

        $response = $this->putJson("/api/admin/customers/{$customer2->id}", [
            'name' => 'Updated Name',
            'email' => 'existing@example.com'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_delete_customer_without_orders()
    {
        $customer = Customer::factory()->create();

        $response = $this->deleteJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_cannot_delete_customer_with_orders()
    {
        $customer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $customer->id]);

        $response = $this->deleteJson("/api/admin/customers/{$customer->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete customer with existing orders. Use soft delete instead.'
            ]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_can_soft_delete_customer()
    {
        $customer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $customer->id]);

        $response = $this->deleteJson("/api/admin/customers/{$customer->id}/soft-delete");

        $response->assertStatus(200);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_can_get_customer_statistics()
    {
        $customer = Customer::factory()->create();

        // Create orders with different statuses
        $order1 = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'total_amount' => 100.00,
            'payment_status' => 'paid'
        ]);
        $order2 = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 150.00,
            'payment_status' => 'partially_paid'
        ]);

        // Create payments
        Payment::factory()->create([
            'order_id' => $order1->id,
            'amount' => 100.00
        ]);
        Payment::factory()->create([
            'order_id' => $order2->id,
            'amount' => 75.00
        ]);

        $response = $this->getJson("/api/admin/customers/{$customer->id}/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_orders',
                    'completed_orders',
                    'pending_orders',
                    'cancelled_orders',
                    'total_spent',
                    'total_paid',
                    'outstanding_balance',
                    'payment_status_breakdown',
                    'average_order_value',
                    'first_order_date',
                    'last_order_date'
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['total_orders']);
        $this->assertEquals(1, $data['completed_orders']);
        $this->assertEquals(1, $data['pending_orders']);
        $this->assertEquals(250.00, $data['total_spent']);
        $this->assertEquals(175.00, $data['total_paid']);
        $this->assertEquals(75.00, $data['outstanding_balance']);
    }

    public function test_requires_authentication()
    {
        // Remove authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/admin/customers');

        $response->assertStatus(401);
    }

    public function test_can_paginate_customers()
    {
        Customer::factory()->count(20)->create();

        $response = $this->getJson('/api/admin/customers?per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page'
                ],
                'links'
            ]);

        $this->assertEquals(5, count($response->json('data')));
        $this->assertEquals(5, $response->json('meta.per_page'));
    }

    public function test_can_sort_customers()
    {
        Customer::factory()->create(['name' => 'Alice', 'created_at' => now()->subDays(2)]);
        Customer::factory()->create(['name' => 'Bob', 'created_at' => now()->subDays(1)]);
        Customer::factory()->create(['name' => 'Charlie', 'created_at' => now()]);

        // Sort by name ascending
        $response = $this->getJson('/api/admin/customers?sort_by=name&sort_order=asc');
        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $names);

        // Sort by created_at descending (default)
        $response = $this->getJson('/api/admin/customers?sort_by=created_at&sort_order=desc');
        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Charlie', 'Bob', 'Alice'], $names);
    }
}
