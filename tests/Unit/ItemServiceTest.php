<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ItemService $itemService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemService = new ItemService();
    }

    public function test_can_create_item()
    {
        $itemData = [
            'name' => 'Test Product',
            'description' => 'A test product description',
            'price' => 29.99,
            'category' => 'Electronics',
            'stock_quantity' => 100,
            'is_active' => true,
        ];

        $item = $this->itemService->createItem($itemData);

        $this->assertInstanceOf(Item::class, $item);
        $this->assertEquals('Test Product', $item->name);
        $this->assertEquals('A test product description', $item->description);
        $this->assertEquals('29.99', $item->price);
        $this->assertEquals('Electronics', $item->category);
        $this->assertEquals(100, $item->stock_quantity);
        $this->assertTrue($item->is_active);
    }

    public function test_can_update_item()
    {
        $item = Item::factory()->create([
            'name' => 'Original Name',
            'price' => 25.00,
            'stock_quantity' => 50,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'price' => 35.00,
            'stock_quantity' => 75,
        ];

        $updatedItem = $this->itemService->updateItem($item, $updateData);

        $this->assertEquals('Updated Name', $updatedItem->name);
        $this->assertEquals('35.00', $updatedItem->price);
        $this->assertEquals(75, $updatedItem->stock_quantity);
    }

    public function test_can_soft_delete_item()
    {
        $item = Item::factory()->create();

        $result = $this->itemService->deleteItem($item);

        $this->assertTrue($result);
        $this->assertSoftDeleted('items', ['id' => $item->id]);
        $this->assertNotNull($item->fresh()->deleted_at);
    }

    public function test_can_restore_soft_deleted_item()
    {
        $item = Item::factory()->create();
        $item->delete(); // Soft delete

        $result = $this->itemService->restoreItem($item);

        $this->assertTrue($result);
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'deleted_at' => null,
        ]);
    }

    public function test_can_get_active_items()
    {
        Item::factory()->create(['is_active' => true]);
        Item::factory()->create(['is_active' => false]);
        Item::factory()->create(['is_active' => true]);

        $activeItems = $this->itemService->getActiveItems();

        $this->assertCount(2, $activeItems);
        $activeItems->each(function ($item) {
            $this->assertTrue($item->is_active);
        });
    }

    public function test_can_get_items_by_category()
    {
        Item::factory()->create(['category' => 'Electronics']);
        Item::factory()->create(['category' => 'Books']);
        Item::factory()->create(['category' => 'Electronics']);

        $electronicsItems = $this->itemService->getItemsByCategory('Electronics');

        $this->assertCount(2, $electronicsItems);
        $electronicsItems->each(function ($item) {
            $this->assertEquals('Electronics', $item->category);
        });
    }

    public function test_can_get_low_stock_items()
    {
        Item::factory()->create(['stock_quantity' => 15, 'is_active' => true]);
        Item::factory()->create(['stock_quantity' => 5, 'is_active' => true]);
        Item::factory()->create(['stock_quantity' => 0, 'is_active' => true]);
        Item::factory()->create(['stock_quantity' => 5, 'is_active' => false]); // Inactive

        $lowStockItems = $this->itemService->getLowStockItems();

        $this->assertCount(2, $lowStockItems); // Only active items with stock <= 10
        $lowStockItems->each(function ($item) {
            $this->assertLessThanOrEqual(10, $item->stock_quantity);
            $this->assertTrue($item->is_active);
        });
    }

    public function test_can_get_low_stock_items_with_custom_threshold()
    {
        Item::factory()->create(['stock_quantity' => 25, 'is_active' => true]);
        Item::factory()->create(['stock_quantity' => 15, 'is_active' => true]);
        Item::factory()->create(['stock_quantity' => 5, 'is_active' => true]);

        $lowStockItems = $this->itemService->getLowStockItems(20);

        $this->assertCount(2, $lowStockItems); // Items with stock <= 20
        $lowStockItems->each(function ($item) {
            $this->assertLessThanOrEqual(20, $item->stock_quantity);
        });
    }

    public function test_can_update_stock_quantity()
    {
        $item = Item::factory()->create(['stock_quantity' => 100]);

        $updatedItem = $this->itemService->updateStock($item, 150);

        $this->assertEquals(150, $updatedItem->stock_quantity);
    }

    public function test_can_adjust_stock_quantity()
    {
        $item = Item::factory()->create(['stock_quantity' => 100]);

        // Increase stock
        $updatedItem = $this->itemService->adjustStock($item, 25);
        $this->assertEquals(125, $updatedItem->stock_quantity);

        // Decrease stock
        $updatedItem = $this->itemService->adjustStock($item, -50);
        $this->assertEquals(75, $updatedItem->stock_quantity);
    }

    public function test_prevents_negative_stock_adjustment()
    {
        $item = Item::factory()->create(['stock_quantity' => 10]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock. Available: 10, Requested: 15');

        $this->itemService->adjustStock($item, -15);
    }

    public function test_can_check_stock_availability()
    {
        $item = Item::factory()->create([
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $this->assertTrue($this->itemService->isStockAvailable($item, 25));
        $this->assertTrue($this->itemService->isStockAvailable($item, 50));
        $this->assertFalse($this->itemService->isStockAvailable($item, 75));
    }

    public function test_stock_not_available_for_inactive_items()
    {
        $item = Item::factory()->create([
            'stock_quantity' => 50,
            'is_active' => false,
        ]);

        $this->assertFalse($this->itemService->isStockAvailable($item, 25));
    }

    public function test_can_get_item_statistics()
    {
        // Create items with different categories and stock levels
        Item::factory()->create([
            'category' => 'Electronics',
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        Item::factory()->create([
            'category' => 'Books',
            'stock_quantity' => 5, // Low stock
            'is_active' => true,
        ]);

        Item::factory()->create([
            'category' => 'Electronics',
            'stock_quantity' => 0, // Out of stock
            'is_active' => true,
        ]);

        Item::factory()->create([
            'category' => 'Books',
            'stock_quantity' => 50,
            'is_active' => false, // Inactive
        ]);

        $stats = $this->itemService->getItemStatistics();

        $this->assertEquals(4, $stats['total_items']);
        $this->assertEquals(3, $stats['active_items']);
        $this->assertEquals(1, $stats['inactive_items']);
        $this->assertEquals(2, $stats['low_stock_items']);
        $this->assertEquals(1, $stats['out_of_stock_items']);
        $this->assertEquals(155, $stats['total_stock_value']);

        $this->assertArrayHasKey('Electronics', $stats['items_by_category']);
        $this->assertArrayHasKey('Books', $stats['items_by_category']);
        $this->assertEquals(2, $stats['items_by_category']['Electronics']['count']);
        $this->assertEquals(2, $stats['items_by_category']['Books']['count']);
    }

    public function test_can_search_items()
    {
        Item::factory()->create(['name' => 'iPhone 13', 'category' => 'Electronics']);
        Item::factory()->create(['name' => 'Samsung Galaxy', 'category' => 'Electronics']);
        Item::factory()->create(['name' => 'MacBook Pro', 'category' => 'Electronics']);
        Item::factory()->create(['name' => 'The Great Gatsby', 'category' => 'Books']);

        // Search by name
        $results = $this->itemService->searchItems('iPhone');
        $this->assertCount(1, $results);
        $this->assertEquals('iPhone 13', $results->first()->name);

        // Search by partial name
        $results = $this->itemService->searchItems('Mac');
        $this->assertCount(1, $results);
        $this->assertEquals('MacBook Pro', $results->first()->name);

        // Search by category
        $results = $this->itemService->searchItems('Books');
        $this->assertCount(1, $results);
        $this->assertEquals('The Great Gatsby', $results->first()->name);
    }

    public function test_can_get_popular_items()
    {
        $item1 = Item::factory()->create(['name' => 'Popular Item 1']);
        $item2 = Item::factory()->create(['name' => 'Popular Item 2']);
        $item3 = Item::factory()->create(['name' => 'Less Popular Item']);

        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();

        // Item 1 ordered 3 times
        OrderItem::factory()->create(['order_id' => $order1->id, 'item_id' => $item1->id, 'quantity' => 2]);
        OrderItem::factory()->create(['order_id' => $order2->id, 'item_id' => $item1->id, 'quantity' => 1]);

        // Item 2 ordered 2 times
        OrderItem::factory()->create(['order_id' => $order1->id, 'item_id' => $item2->id, 'quantity' => 1]);
        OrderItem::factory()->create(['order_id' => $order2->id, 'item_id' => $item2->id, 'quantity' => 2]);

        // Item 3 ordered 1 time
        OrderItem::factory()->create(['order_id' => $order1->id, 'item_id' => $item3->id, 'quantity' => 1]);

        $popularItems = $this->itemService->getPopularItems(2);

        $this->assertCount(2, $popularItems);
        $this->assertEquals('Popular Item 1', $popularItems->first()->name);
        $this->assertEquals(3, $popularItems->first()->total_quantity); // 2 + 1
        $this->assertEquals('Popular Item 2', $popularItems->get(1)->name);
        $this->assertEquals(3, $popularItems->get(1)->total_quantity); // 1 + 2
    }

    public function test_validates_item_data()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item name is required.');

        $this->itemService->createItem([
            'price' => 25.00,
            'category' => 'Electronics',
        ]);
    }

    public function test_validates_item_price()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item price must be greater than zero.');

        $this->itemService->createItem([
            'name' => 'Test Item',
            'price' => -5.00,
            'category' => 'Electronics',
        ]);
    }

    public function test_validates_stock_quantity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock quantity cannot be negative.');

        $this->itemService->createItem([
            'name' => 'Test Item',
            'price' => 25.00,
            'category' => 'Electronics',
            'stock_quantity' => -10,
        ]);
    }

    public function test_can_bulk_update_prices()
    {
        $item1 = Item::factory()->create(['price' => 100.00, 'category' => 'Electronics']);
        $item2 = Item::factory()->create(['price' => 50.00, 'category' => 'Electronics']);
        $item3 = Item::factory()->create(['price' => 25.00, 'category' => 'Books']);

        // Increase Electronics category prices by 10%
        $result = $this->itemService->bulkUpdatePrices('Electronics', 10, 'percentage');

        $this->assertTrue($result);

        $item1->refresh();
        $item2->refresh();
        $item3->refresh();

        $this->assertEquals('110.00', $item1->price); // 100 + 10%
        $this->assertEquals('55.00', $item2->price);  // 50 + 10%
        $this->assertEquals('25.00', $item3->price);  // Books category unchanged
    }

    public function test_can_bulk_update_prices_with_fixed_amount()
    {
        $item1 = Item::factory()->create(['price' => 100.00, 'category' => 'Electronics']);
        $item2 = Item::factory()->create(['price' => 50.00, 'category' => 'Electronics']);

        // Increase Electronics category prices by $5
        $result = $this->itemService->bulkUpdatePrices('Electronics', 5, 'fixed');

        $this->assertTrue($result);

        $item1->refresh();
        $item2->refresh();

        $this->assertEquals('105.00', $item1->price); // 100 + 5
        $this->assertEquals('55.00', $item2->price);  // 50 + 5
    }

    public function test_can_get_categories()
    {
        Item::factory()->create(['category' => 'Electronics']);
        Item::factory()->create(['category' => 'Books']);
        Item::factory()->create(['category' => 'Electronics']); // Duplicate
        Item::factory()->create(['category' => 'Clothing']);

        $categories = $this->itemService->getCategories();

        $this->assertCount(3, $categories);
        $this->assertContains('Electronics', $categories);
        $this->assertContains('Books', $categories);
        $this->assertContains('Clothing', $categories);
    }
}
