<?php

namespace Tests\Feature;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_resource_returns_correct_structure(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
        ]);

        $resource = new CustomerResource($customer);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('phone', $array);
        $this->assertArrayHasKey('address', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals('John Doe', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
    }

    public function test_item_resource_returns_correct_structure(): void
    {
        $item = Item::factory()->create([
            'name' => 'Test Item',
            'description' => 'Test Description',
            'price' => 99.99,
            'category' => 'Electronics',
            'stock_quantity' => 5,
        ]);

        $resource = new ItemResource($item);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('price', $array);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('stock_quantity', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('is_low_stock', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals('Test Item', $array['name']);
        $this->assertEquals(99.99, $array['price']);
        $this->assertTrue($array['is_low_stock']); // 5 <= 10
    }

    public function test_order_resource_returns_correct_structure(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-001',
            'status' => 'pending',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'payment_status' => 'unpaid',
        ]);

        $resource = new OrderResource($order);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('order_number', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('subtotal', $array);
        $this->assertArrayHasKey('total_amount', $array);
        $this->assertArrayHasKey('payment_status', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals('ORD-001', $array['order_number']);
        $this->assertEquals('pending', $array['status']);
        $this->assertEquals(100.00, $array['total_amount']);
    }

    public function test_payment_resource_returns_correct_structure(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'payment_method' => 'cash',
            'notes' => 'Partial payment',
        ]);

        $resource = new PaymentResource($payment);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('order_id', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('payment_method', $array);
        $this->assertArrayHasKey('notes', $array);
        $this->assertArrayHasKey('payment_date', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertEquals(50.00, $array['amount']);
        $this->assertEquals('cash', $array['payment_method']);
        $this->assertEquals('Partial payment', $array['notes']);
    }
}
