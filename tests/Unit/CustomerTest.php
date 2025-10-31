<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_created_with_required_fields()
    {
        $customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
        ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
        ]);
    }

    public function test_customer_can_be_created_with_only_name()
    {
        $customer = Customer::create([
            'name' => 'Jane Doe',
        ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Doe',
        ]);

        $this->assertNull($customer->email);
        $this->assertNull($customer->phone);
        $this->assertNull($customer->address);
    }

    public function test_customer_has_orders_relationship()
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $this->assertTrue($customer->orders->contains($order));
        $this->assertEquals(1, $customer->orders->count());
    }

    public function test_customer_total_orders_attribute()
    {
        $customer = Customer::factory()->create();
        Order::factory()->count(3)->create(['customer_id' => $customer->id]);

        $customer->refresh();
        $this->assertEquals(3, $customer->total_orders);
    }

    public function test_customer_total_spent_attribute()
    {
        $customer = Customer::factory()->create();
        Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150.00
        ]);

        $customer->refresh();
        $this->assertEquals(250.00, $customer->total_spent);
    }

    public function test_customer_total_paid_attribute()
    {
        $customer = Customer::factory()->create();
        $order1 = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00
        ]);
        $order2 = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150.00
        ]);

        Payment::factory()->create([
            'order_id' => $order1->id,
            'amount' => 50.00
        ]);
        Payment::factory()->create([
            'order_id' => $order2->id,
            'amount' => 150.00
        ]);

        $customer->refresh();
        $this->assertEquals(200.00, $customer->total_paid);
    }

    public function test_customer_can_be_soft_deleted()
    {
        $customer = Customer::factory()->create();
        $customerId = $customer->id;

        $customer->delete();

        $this->assertSoftDeleted('customers', ['id' => $customerId]);
        $this->assertNotNull($customer->fresh()->deleted_at);
    }

    public function test_soft_deleted_customers_can_be_restored()
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $customer->restore();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null
        ]);
    }
}
