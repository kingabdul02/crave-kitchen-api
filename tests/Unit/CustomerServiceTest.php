<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerService = new CustomerService();
    }

    public function test_can_create_customer()
    {
        $customerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St, City, State',
        ];

        $customer = $this->customerService->createCustomer($customerData);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('John Doe', $customer->name);
        $this->assertEquals('john@example.com', $customer->email);
        $this->assertEquals('1234567890', $customer->phone);
        $this->assertEquals('123 Main St, City, State', $customer->address);
    }

    public function test_can_create_customer_with_only_name()
    {
        $customerData = [
            'name' => 'Jane Doe',
        ];

        $customer = $this->customerService->createCustomer($customerData);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('Jane Doe', $customer->name);
        $this->assertNull($customer->email);
        $this->assertNull($customer->phone);
        $this->assertNull($customer->address);
    }

    public function test_can_update_customer()
    {
        $customer = Customer::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '9876543210',
        ];

        $updatedCustomer = $this->customerService->updateCustomer($customer, $updateData);

        $this->assertEquals('Updated Name', $updatedCustomer->name);
        $this->assertEquals('updated@example.com', $updatedCustomer->email);
        $this->assertEquals('9876543210', $updatedCustomer->phone);
    }

    public function test_can_soft_delete_customer()
    {
        $customer = Customer::factory()->create();

        $result = $this->customerService->deleteCustomer($customer);

        $this->assertTrue($result);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertNotNull($customer->fresh()->deleted_at);
    }

    public function test_can_restore_soft_deleted_customer()
    {
        $customer = Customer::factory()->create();
        $customer->delete(); // Soft delete

        $result = $this->customerService->restoreCustomer($customer);

        $this->assertTrue($result);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_get_customer_summary()
    {
        $customer = Customer::factory()->create();

        // Create orders for the customer
        $order1 = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        $order2 = Order::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150.00,
            'status' => 'pending',
        ]);

        // Create payments
        Payment::factory()->create([
            'order_id' => $order1->id,
            'amount' => 100.00,
        ]);

        Payment::factory()->create([
            'order_id' => $order2->id,
            'amount' => 75.00,
        ]);

        $summary = $this->customerService->getCustomerSummary($customer);

        $this->assertEquals($customer->id, $summary['customer']['id']);
        $this->assertEquals($customer->name, $summary['customer']['name']);
        $this->assertEquals(2, $summary['orders']['total_count']);
        $this->assertEquals(250.00, $summary['orders']['total_value']);
        $this->assertEquals(125.00, $summary['orders']['average_order_value']);
        $this->assertEquals(175.00, $summary['payments']['total_paid']);
        $this->assertEquals(75.00, $summary['payments']['outstanding_balance']);

        $this->assertArrayHasKey('completed', $summary['orders']['by_status']);
        $this->assertArrayHasKey('pending', $summary['orders']['by_status']);
        $this->assertEquals(1, $summary['orders']['by_status']['completed']);
        $this->assertEquals(1, $summary['orders']['by_status']['pending']);
    }

    public function test_can_get_customer_order_history()
    {
        $customer = Customer::factory()->create();

        $order1 = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-001',
            'total_amount' => 100.00,
            'created_at' => now()->subDays(5),
        ]);

        $order2 = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-002',
            'total_amount' => 150.00,
            'created_at' => now()->subDays(2),
        ]);

        $orderHistory = $this->customerService->getCustomerOrderHistory($customer);

        $this->assertCount(2, $orderHistory);

        // Should be ordered by created_at desc (most recent first)
        $this->assertEquals('ORD-002', $orderHistory->first()->order_number);
        $this->assertEquals('ORD-001', $orderHistory->last()->order_number);
    }

    public function test_can_get_customer_order_history_with_limit()
    {
        $customer = Customer::factory()->create();

        // Create 5 orders
        for ($i = 1; $i <= 5; $i++) {
            Order::factory()->create([
                'customer_id' => $customer->id,
                'order_number' => "ORD-00{$i}",
                'created_at' => now()->subDays($i),
            ]);
        }

        $orderHistory = $this->customerService->getCustomerOrderHistory($customer, 3);

        $this->assertCount(3, $orderHistory);

        // Should get the 3 most recent orders
        $this->assertEquals('ORD-001', $orderHistory->first()->order_number);
        $this->assertEquals('ORD-003', $orderHistory->last()->order_number);
    }

    public function test_can_search_customers()
    {
        Customer::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        Customer::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '0987654321',
        ]);

        Customer::factory()->create([
            'name' => 'Bob Johnson',
            'email' => 'bob@test.com',
            'phone' => '5555555555',
        ]);

        // Search by name
        $results = $this->customerService->searchCustomers('John');
        $this->assertCount(2, $results); // John Doe and Bob Johnson

        // Search by email
        $results = $this->customerService->searchCustomers('example.com');
        $this->assertCount(2, $results); // John and Jane

        // Search by phone
        $results = $this->customerService->searchCustomers('1234');
        $this->assertCount(1, $results); // John Doe
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function test_can_get_top_customers()
    {
        $customer1 = Customer::factory()->create(['name' => 'High Value Customer']);
        $customer2 = Customer::factory()->create(['name' => 'Medium Value Customer']);
        $customer3 = Customer::factory()->create(['name' => 'Low Value Customer']);

        // Customer 1: $300 total
        Order::factory()->create([
            'customer_id' => $customer1->id,
            'total_amount' => 200.00,
        ]);
        Order::factory()->create([
            'customer_id' => $customer1->id,
            'total_amount' => 100.00,
        ]);

        // Customer 2: $150 total
        Order::factory()->create([
            'customer_id' => $customer2->id,
            'total_amount' => 150.00,
        ]);

        // Customer 3: $50 total
        Order::factory()->create([
            'customer_id' => $customer3->id,
            'total_amount' => 50.00,
        ]);

        $topCustomers = $this->customerService->getTopCustomers(2);

        $this->assertCount(2, $topCustomers);
        $this->assertEquals('High Value Customer', $topCustomers->first()->name);
        $this->assertEquals(300.00, $topCustomers->first()->total_spent);
        $this->assertEquals('Medium Value Customer', $topCustomers->get(1)->name);
        $this->assertEquals(150.00, $topCustomers->get(1)->total_spent);
    }

    public function test_can_get_customer_statistics()
    {
        // Create customers with different order patterns
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $customer3 = Customer::factory()->create();

        // Customer 1: 2 orders, $250 total
        Order::factory()->create([
            'customer_id' => $customer1->id,
            'total_amount' => 150.00,
        ]);
        Order::factory()->create([
            'customer_id' => $customer1->id,
            'total_amount' => 100.00,
        ]);

        // Customer 2: 1 order, $75 total
        Order::factory()->create([
            'customer_id' => $customer2->id,
            'total_amount' => 75.00,
        ]);

        // Customer 3: No orders

        $stats = $this->customerService->getCustomerStatistics();

        $this->assertEquals(3, $stats['total_customers']);
        $this->assertEquals(2, $stats['customers_with_orders']);
        $this->assertEquals(1, $stats['customers_without_orders']);
        $this->assertEquals(325.00, $stats['total_customer_value']);
        $this->assertEquals(162.50, $stats['average_customer_value']);
        $this->assertEquals(1.5, $stats['average_orders_per_customer']);
    }

    public function test_validates_customer_data()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer name is required.');

        $this->customerService->createCustomer([
            'email' => 'test@example.com',
        ]);
    }

    public function test_validates_email_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format.');

        $this->customerService->createCustomer([
            'name' => 'Test Customer',
            'email' => 'invalid-email',
        ]);
    }

    public function test_can_get_customers_with_outstanding_balance()
    {
        $customer1 = Customer::factory()->create(['name' => 'Customer with Balance']);
        $customer2 = Customer::factory()->create(['name' => 'Customer Paid in Full']);
        $customer3 = Customer::factory()->create(['name' => 'Customer No Orders']);

        // Customer 1: $100 order, $50 paid, $50 outstanding
        $order1 = Order::factory()->create([
            'customer_id' => $customer1->id,
            'total_amount' => 100.00,
        ]);
        Payment::factory()->create([
            'order_id' => $order1->id,
            'amount' => 50.00,
        ]);

        // Customer 2: $150 order, $150 paid, $0 outstanding
        $order2 = Order::factory()->create([
            'customer_id' => $customer2->id,
            'total_amount' => 150.00,
        ]);
        Payment::factory()->create([
            'order_id' => $order2->id,
            'amount' => 150.00,
        ]);

        $customersWithBalance = $this->customerService->getCustomersWithOutstandingBalance();

        $this->assertCount(1, $customersWithBalance);
        $this->assertEquals('Customer with Balance', $customersWithBalance->first()->name);
        $this->assertEquals(50.00, $customersWithBalance->first()->outstanding_balance);
    }

    public function test_can_get_recent_customers()
    {
        Customer::factory()->create([
            'name' => 'Old Customer',
            'created_at' => now()->subDays(10),
        ]);

        Customer::factory()->create([
            'name' => 'Recent Customer 1',
            'created_at' => now()->subDays(2),
        ]);

        Customer::factory()->create([
            'name' => 'Recent Customer 2',
            'created_at' => now()->subDay(),
        ]);

        $recentCustomers = $this->customerService->getRecentCustomers(7); // Last 7 days

        $this->assertCount(2, $recentCustomers);

        // Should be ordered by created_at desc
        $this->assertEquals('Recent Customer 2', $recentCustomers->first()->name);
        $this->assertEquals('Recent Customer 1', $recentCustomers->last()->name);
    }

    public function test_can_merge_customers()
    {
        $primaryCustomer = Customer::factory()->create([
            'name' => 'Primary Customer',
            'email' => 'primary@example.com',
        ]);

        $secondaryCustomer = Customer::factory()->create([
            'name' => 'Secondary Customer',
            'phone' => '1234567890',
        ]);

        // Create orders for secondary customer
        Order::factory()->count(2)->create([
            'customer_id' => $secondaryCustomer->id,
        ]);

        $result = $this->customerService->mergeCustomers($primaryCustomer, $secondaryCustomer, [
            'phone' => $secondaryCustomer->phone, // Take phone from secondary
        ]);

        $this->assertTrue($result);

        // Primary customer should have updated info
        $primaryCustomer->refresh();
        $this->assertEquals('1234567890', $primaryCustomer->phone);

        // Orders should be transferred to primary customer
        $this->assertEquals(2, $primaryCustomer->orders()->count());

        // Secondary customer should be soft deleted
        $this->assertSoftDeleted('customers', ['id' => $secondaryCustomer->id]);
    }
}
