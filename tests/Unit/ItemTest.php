<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_can_be_created_with_valid_data()
    {
        $itemData = [
            'name' => 'Test Item',
            'description' => 'A test item description',
            'price' => 29.99,
            'category' => 'Electronics',
            'stock_quantity' => 100,
            'is_active' => true,
        ];

        $item = Item::create($itemData);

        $this->assertInstanceOf(Item::class, $item);
        $this->assertEquals('Test Item', $item->name);
        $this->assertEquals(29.99, $item->price);
        $this->assertEquals('Electronics', $item->category);
        $this->assertEquals(100, $item->stock_quantity);
        $this->assertTrue($item->is_active);
    }

    public function test_item_price_is_cast_to_decimal()
    {
        $item = Item::factory()->create(['price' => '29.99']);

        // Laravel's decimal cast returns a string, not float
        $this->assertIsString($item->price);
        $this->assertEquals('29.99', $item->price);
        $this->assertEquals(29.99, (float) $item->price);
    }

    public function test_item_stock_quantity_is_cast_to_integer()
    {
        $item = Item::factory()->create(['stock_quantity' => '100']);

        $this->assertIsInt($item->stock_quantity);
        $this->assertEquals(100, $item->stock_quantity);
    }

    public function test_item_is_active_is_cast_to_boolean()
    {
        $item = Item::factory()->create(['is_active' => 1]);

        $this->assertIsBool($item->is_active);
        $this->assertTrue($item->is_active);
    }

    public function test_item_is_available_attribute()
    {
        // Active item with stock
        $availableItem = Item::factory()->create([
            'is_active' => true,
            'stock_quantity' => 10,
        ]);
        $this->assertTrue($availableItem->is_available);

        // Inactive item with stock
        $inactiveItem = Item::factory()->create([
            'is_active' => false,
            'stock_quantity' => 10,
        ]);
        $this->assertFalse($inactiveItem->is_available);

        // Active item without stock
        $outOfStockItem = Item::factory()->create([
            'is_active' => true,
            'stock_quantity' => 0,
        ]);
        $this->assertFalse($outOfStockItem->is_available);
    }

    public function test_item_is_low_stock_attribute()
    {
        // Item with stock above threshold
        $highStockItem = Item::factory()->create(['stock_quantity' => 20]);
        $this->assertFalse($highStockItem->is_low_stock);

        // Item with stock at threshold
        $thresholdStockItem = Item::factory()->create(['stock_quantity' => 10]);
        $this->assertTrue($thresholdStockItem->is_low_stock);

        // Item with stock below threshold
        $lowStockItem = Item::factory()->create(['stock_quantity' => 5]);
        $this->assertTrue($lowStockItem->is_low_stock);
    }

    public function test_item_active_scope()
    {
        Item::factory()->create(['is_active' => true]);
        Item::factory()->create(['is_active' => false]);

        $activeItems = Item::active()->get();

        $this->assertCount(1, $activeItems);
        $this->assertTrue($activeItems->first()->is_active);
    }

    public function test_item_by_category_scope()
    {
        Item::factory()->create(['category' => 'Electronics']);
        Item::factory()->create(['category' => 'Books']);
        Item::factory()->create(['category' => 'Electronics']);

        $electronicsItems = Item::byCategory('Electronics')->get();

        $this->assertCount(2, $electronicsItems);
        $electronicsItems->each(function ($item) {
            $this->assertEquals('Electronics', $item->category);
        });
    }

    public function test_item_has_order_items_relationship()
    {
        $item = Item::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $item->orderItems());
    }

    public function test_item_soft_deletes()
    {
        $item = Item::factory()->create();
        $itemId = $item->id;

        $item->delete();

        // Item should be soft deleted
        $this->assertSoftDeleted('items', ['id' => $itemId]);

        // Item should not be found in normal queries
        $this->assertNull(Item::find($itemId));

        // Item should be found with trashed
        $this->assertNotNull(Item::withTrashed()->find($itemId));
    }
}
