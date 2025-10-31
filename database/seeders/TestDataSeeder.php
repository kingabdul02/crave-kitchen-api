<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds for testing purposes.
     */
    public function run(): void
    {
        // Create customers
        $customers = $this->createCustomers();

        // Create items
        $items = $this->createItems();

        // Create orders with items and payments
        $this->createOrdersWithPayments($customers, $items);
    }

    private function createCustomers()
    {
        $customers = collect();

        // High-value customer
        $customers->push(Customer::factory()->create([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St, New York, NY 10001',
        ]));

        // Regular customers
        $customers->push(Customer::factory()->create([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'phone' => '2345678901',
            'address' => '456 Oak Ave, Los Angeles, CA 90210',
        ]));

        $customers->push(Customer::factory()->create([
            'name' => 'Carol Davis',
            'email' => 'carol@example.com',
            'phone' => '3456789012',
            'address' => '789 Pine St, Chicago, IL 60601',
        ]));

        // Customer with minimal info
        $customers->push(Customer::factory()->create([
            'name' => 'David Wilson',
            'email' => null,
            'phone' => '4567890123',
            'address' => null,
        ]));

        // Customer with outstanding balance
        $customers->push(Customer::factory()->create([
            'name' => 'Eva Brown',
            'email' => 'eva@example.com',
            'phone' => '5678901234',
            'address' => '321 Elm St, Houston, TX 77001',
        ]));

        return $customers;
    }

    private function createItems()
    {
        $items = collect();

        // Electronics category
        $items->push(Item::factory()->create([
            'name' => 'iPhone 15 Pro',
            'description' => 'Latest iPhone with advanced camera system',
            'price' => 999.99,
            'category' => 'Electronics',
            'stock_quantity' => 25,
            'is_active' => true,
        ]));

        $items->push(Item::factory()->create([
            'name' => 'MacBook Air M2',
            'description' => 'Lightweight laptop with M2 chip',
            'price' => 1199.99,
            'category' => 'Electronics',
            'stock_quantity' => 15,
            'is_active' => true,
        ]));

        $items->push(Item::factory()->create([
            'name' => 'AirPods Pro',
            'description' => 'Wireless earbuds with noise cancellation',
            'price' => 249.99,
            'category' => 'Electronics',
            'stock_quantity' => 50,
            'is_active' => true,
        ]));

        // Books category
        $items->push(Item::factory()->create([
            'name' => 'The Great Gatsby',
            'description' => 'Classic American novel by F. Scott Fitzgerald',
            'price' => 12.99,
            'category' => 'Books',
            'stock_quantity' => 100,
            'is_active' => true,
        ]));

        $items->push(Item::factory()->create([
            'name' => '1984',
            'description' => 'Dystopian novel by George Orwell',
            'price' => 13.99,
            'category' => 'Books',
            'stock_quantity' => 75,
            'is_active' => true,
        ]));

        // Clothing category
        $items->push(Item::factory()->create([
            'name' => 'Premium Cotton T-Shirt',
            'description' => 'Comfortable cotton t-shirt in various colors',
            'price' => 29.99,
            'category' => 'Clothing',
            'stock_quantity' => 200,
            'is_active' => true,
        ]));

        $items->push(Item::factory()->create([
            'name' => 'Denim Jeans',
            'description' => 'Classic blue denim jeans',
            'price' => 79.99,
            'category' => 'Clothing',
            'stock_quantity' => 80,
            'is_active' => true,
        ]));

        // Low stock items
        $items->push(Item::factory()->create([
            'name' => 'Limited Edition Watch',
            'description' => 'Luxury watch with limited availability',
            'price' => 599.99,
            'category' => 'Accessories',
            'stock_quantity' => 3, // Low stock
            'is_active' => true,
        ]));

        // Out of stock item
        $items->push(Item::factory()->create([
            'name' => 'Sold Out Sneakers',
            'description' => 'Popular sneakers that are currently out of stock',
            'price' => 149.99,
            'category' => 'Footwear',
            'stock_quantity' => 0, // Out of stock
            'is_active' => true,
        ]));

        // Inactive item
        $items->push(Item::factory()->create([
            'name' => 'Discontinued Product',
            'description' => 'Product that is no longer available',
            'price' => 99.99,
            'category' => 'Electronics',
            'stock_quantity' => 10,
            'is_active' => false, // Inactive
        ]));

        return $items;
    }

    private function createOrdersWithPayments($customers, $items)
    {
        // Alice Johnson - High value customer with multiple completed orders
        $alice = $customers->first();

        // Order 1: Large electronics order - fully paid
        $order1 = Order::factory()->create([
            'customer_id' => $alice->id,
            'status' => 'completed',
            'subtotal' => 2449.97,
            'total_amount' => 2449.97,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(30),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'item_id' => $items->where('name', 'iPhone 15 Pro')->first()->id,
            'quantity' => 1,
            'unit_price' => 999.99,
            'total_price' => 999.99,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'item_id' => $items->where('name', 'MacBook Air M2')->first()->id,
            'quantity' => 1,
            'unit_price' => 1199.99,
            'total_price' => 1199.99,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'item_id' => $items->where('name', 'AirPods Pro')->first()->id,
            'quantity' => 1,
            'unit_price' => 249.99,
            'total_price' => 249.99,
        ]);

        Payment::factory()->create([
            'order_id' => $order1->id,
            'amount' => 2449.97,
            'payment_method' => 'transfer',
            'notes' => 'Bank transfer payment',
            'payment_date' => now()->subDays(30),
        ]);

        // Order 2: Recent order with discount - partially paid
        $order2 = Order::factory()->create([
            'customer_id' => $alice->id,
            'status' => 'processed',
            'subtotal' => 109.98,
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'total_amount' => 98.98,
            'payment_status' => 'partially_paid',
            'created_at' => now()->subDays(5),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'item_id' => $items->where('name', 'Denim Jeans')->first()->id,
            'quantity' => 1,
            'unit_price' => 79.99,
            'total_price' => 79.99,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'item_id' => $items->where('name', 'Premium Cotton T-Shirt')->first()->id,
            'quantity' => 1,
            'unit_price' => 29.99,
            'total_price' => 29.99,
        ]);

        Payment::factory()->create([
            'order_id' => $order2->id,
            'amount' => 50.00,
            'payment_method' => 'cash',
            'notes' => 'Partial cash payment',
            'payment_date' => now()->subDays(5),
        ]);

        // Bob Smith - Regular customer with mixed payment status
        $bob = $customers->get(1);

        $order3 = Order::factory()->create([
            'customer_id' => $bob->id,
            'status' => 'completed',
            'subtotal' => 26.98,
            'total_amount' => 26.98,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(15),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order3->id,
            'item_id' => $items->where('name', 'The Great Gatsby')->first()->id,
            'quantity' => 1,
            'unit_price' => 12.99,
            'total_price' => 12.99,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order3->id,
            'item_id' => $items->where('name', '1984')->first()->id,
            'quantity' => 1,
            'unit_price' => 13.99,
            'total_price' => 13.99,
        ]);

        Payment::factory()->create([
            'order_id' => $order3->id,
            'amount' => 26.98,
            'payment_method' => 'pos',
            'notes' => 'Card payment',
            'payment_date' => now()->subDays(15),
        ]);

        // Carol Davis - Recent order, pending status
        $carol = $customers->get(2);

        $order4 = Order::factory()->create([
            'customer_id' => $carol->id,
            'status' => 'pending',
            'subtotal' => 599.99,
            'total_amount' => 599.99,
            'payment_status' => 'unpaid',
            'created_at' => now()->subDays(2),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order4->id,
            'item_id' => $items->where('name', 'Limited Edition Watch')->first()->id,
            'quantity' => 1,
            'unit_price' => 599.99,
            'total_price' => 599.99,
        ]);

        // David Wilson - Small order with overpayment
        $david = $customers->get(3);

        $order5 = Order::factory()->create([
            'customer_id' => $david->id,
            'status' => 'completed',
            'subtotal' => 29.99,
            'total_amount' => 29.99,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(7),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order5->id,
            'item_id' => $items->where('name', 'Premium Cotton T-Shirt')->first()->id,
            'quantity' => 1,
            'unit_price' => 29.99,
            'total_price' => 29.99,
        ]);

        Payment::factory()->create([
            'order_id' => $order5->id,
            'amount' => 35.00, // Overpayment
            'payment_method' => 'cash',
            'notes' => 'Customer paid extra as tip',
            'payment_date' => now()->subDays(7),
        ]);

        // Eva Brown - Multiple orders with outstanding balance
        $eva = $customers->get(4);

        $order6 = Order::factory()->create([
            'customer_id' => $eva->id,
            'status' => 'processed',
            'subtotal' => 249.99,
            'total_amount' => 249.99,
            'payment_status' => 'partially_paid',
            'created_at' => now()->subDays(10),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order6->id,
            'item_id' => $items->where('name', 'AirPods Pro')->first()->id,
            'quantity' => 1,
            'unit_price' => 249.99,
            'total_price' => 249.99,
        ]);

        Payment::factory()->create([
            'order_id' => $order6->id,
            'amount' => 100.00,
            'payment_method' => 'transfer',
            'notes' => 'Partial payment',
            'payment_date' => now()->subDays(10),
        ]);

        // Cancelled order
        $order7 = Order::factory()->create([
            'customer_id' => $eva->id,
            'status' => 'cancelled',
            'subtotal' => 149.99,
            'total_amount' => 149.99,
            'payment_status' => 'unpaid',
            'notes' => 'Customer cancelled due to delivery issues',
            'created_at' => now()->subDays(20),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order7->id,
            'item_id' => $items->where('name', 'Sold Out Sneakers')->first()->id,
            'quantity' => 1,
            'unit_price' => 149.99,
            'total_price' => 149.99,
        ]);
    }
}
