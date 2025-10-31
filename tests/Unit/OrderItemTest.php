<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_item_can_be_created_with_valid_data()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create(['price' => 25.50]);

        $orderItemData = [
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 3,
            'unit_price' => 25.50,
            'total_price' => 76.50,
        ];

        $orderItem = OrderItem::create($orderItemData);

        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertEquals($order->id, $orderItem->order_id);
        $this->assertEquals($item->id, $orderItem->item_id);
        $this->assertEquals(3, $orderItem->quantity);
        $this->assertEquals('25.50', $orderItem->unit_price);
        $this->assertEquals('76.50', $orderItem->total_price);
    }

    public function test_order_item_belongs_to_order()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
        ]);

        $this->assertInstanceOf(Order::class, $orderItem->order);
        $this->assertEquals($order->id, $orderItem->order->id);
    }

    public function test_order_item_belongs_to_item()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
        ]);

        $this->assertInstanceOf(Item::class, $orderItem->item);
        $this->assertEquals($item->id, $orderItem->item->id);
    }

    public function test_order_item_calculates_total_price_on_creation()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 4,
            'unit_price' => 12.75,
            // Not providing total_price - should be calculated
        ]);

        $this->assertEquals('51.00', $orderItem->total_price);
    }

    public function test_order_item_recalculates_total_price_on_update()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'total_price' => 20.00,
        ]);

        // Update quantity
        $orderItem->update(['quantity' => 5]);

        $this->assertEquals('50.00', $orderItem->total_price);

        // Update unit price
        $orderItem->update(['unit_price' => 15.00]);

        $this->assertEquals('75.00', $orderItem->total_price);
    }

    public function test_order_item_preserves_provided_total_price_on_creation()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        // Providing a custom total_price (maybe with discount)
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'total_price' => 18.00, // Custom price (with discount)
        ]);

        $this->assertEquals('18.00', $orderItem->total_price);
    }

    public function test_order_item_casts_quantity_to_integer()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => '5',
        ]);

        $this->assertIsInt($orderItem->quantity);
        $this->assertEquals(5, $orderItem->quantity);
    }

    public function test_order_item_casts_decimal_fields_correctly()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'unit_price' => '29.99',
            'total_price' => '59.98',
        ]);

        $this->assertIsString($orderItem->unit_price);
        $this->assertIsString($orderItem->total_price);
        $this->assertEquals('29.99', $orderItem->unit_price);
        $this->assertEquals('59.98', $orderItem->total_price);
    }

    public function test_order_item_fillable_attributes()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItemData = [
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 3,
            'unit_price' => 15.75,
            'total_price' => 47.25,
        ];

        $orderItem = OrderItem::create($orderItemData);

        foreach ($orderItemData as $key => $value) {
            $this->assertEquals($value, $orderItem->$key);
        }
    }

    public function test_order_item_does_not_have_timestamps()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
        ]);

        // OrderItem model has $timestamps = false
        $this->assertNull($orderItem->created_at);
        $this->assertNull($orderItem->updated_at);
    }

    public function test_order_item_handles_zero_quantity()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 0,
            'unit_price' => 10.00,
        ]);

        $this->assertEquals('0.00', $orderItem->total_price);
    }

    public function test_order_item_handles_zero_unit_price()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_price' => 0.00,
        ]);

        $this->assertEquals('0.00', $orderItem->total_price);
    }

    public function test_order_item_handles_decimal_calculations_correctly()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        // Test with decimal values that might cause floating point issues
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 3,
            'unit_price' => 33.33,
        ]);

        // 3 * 33.33 = 99.99
        $this->assertEquals('99.99', $orderItem->total_price);
    }

    public function test_order_item_can_be_updated_independently()
    {
        $order = Order::factory()->create();
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item1->id,
            'quantity' => 2,
            'unit_price' => 10.00,
        ]);

        // Update to different item
        $orderItem->update([
            'item_id' => $item2->id,
            'quantity' => 3,
            'unit_price' => 15.00,
        ]);

        $this->assertEquals($item2->id, $orderItem->item_id);
        $this->assertEquals(3, $orderItem->quantity);
        $this->assertEquals('15.00', $orderItem->unit_price);
        $this->assertEquals('45.00', $orderItem->total_price);
    }

    public function test_order_item_can_be_deleted()
    {
        $order = Order::factory()->create();
        $item = Item::factory()->create();

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item->id,
        ]);

        $orderItemId = $orderItem->id;
        $orderItem->delete();

        $this->assertDatabaseMissing('order_items', ['id' => $orderItemId]);
    }

    public function test_multiple_order_items_for_same_order()
    {
        $order = Order::factory()->create();
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();

        $orderItem1 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item1->id,
            'quantity' => 2,
            'unit_price' => 10.00,
        ]);

        $orderItem2 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => $item2->id,
            'quantity' => 1,
            'unit_price' => 25.00,
        ]);

        $orderItems = $order->orderItems;
        $this->assertCount(2, $orderItems);
        $this->assertTrue($orderItems->contains($orderItem1));
        $this->assertTrue($orderItems->contains($orderItem2));
    }
}
