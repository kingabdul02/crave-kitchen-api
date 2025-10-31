<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate a user for all tests
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    public function test_can_list_items()
    {
        Item::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'price',
                        'category',
                        'stock_quantity',
                        'is_active',
                        'is_low_stock',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);
    }

    public function test_can_list_items_with_pagination()
    {
        Item::factory()->count(20)->create();

        $response = $this->getJson('/api/admin/items?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
                'links'
            ]);
    }

    public function test_can_search_items_by_name()
    {
        Item::factory()->create(['name' => 'iPhone 15']);
        Item::factory()->create(['name' => 'Samsung Galaxy']);
        Item::factory()->create(['name' => 'iPad Pro']);

        $response = $this->getJson('/api/admin/items?search=iPhone');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('iPhone 15', $data[0]['name']);
    }

    public function test_can_filter_items_by_category()
    {
        Item::factory()->create(['category' => 'Electronics']);
        Item::factory()->create(['category' => 'Books']);
        Item::factory()->create(['category' => 'Electronics']);

        $response = $this->getJson('/api/admin/items?category=Electronics');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_can_filter_items_by_active_status()
    {
        Item::factory()->create(['is_active' => true]);
        Item::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/admin/items?is_active=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_active']);
    }

    public function test_can_filter_low_stock_items()
    {
        Item::factory()->create(['stock_quantity' => 5]);
        Item::factory()->create(['stock_quantity' => 20]);

        $response = $this->getJson('/api/admin/items?low_stock=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(5, $data[0]['stock_quantity']);
    }

    public function test_can_create_item()
    {
        $itemData = [
            'name' => 'New Item',
            'description' => 'A new test item',
            'price' => 49.99,
            'category' => 'Test Category',
            'stock_quantity' => 50,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/items', $itemData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'category',
                    'stock_quantity',
                    'is_active',
                ]
            ]);

        $this->assertDatabaseHas('items', [
            'name' => 'New Item',
            'price' => 49.99,
            'category' => 'Test Category',
        ]);
    }

    public function test_create_item_validation_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/admin/items', [
            'name' => '', // Required field
            'price' => -10, // Must be positive
            'stock_quantity' => -5, // Must be positive
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price', 'category', 'stock_quantity']);
    }

    public function test_can_show_item()
    {
        $item = Item::factory()->create();

        $response = $this->getJson("/api/admin/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'category',
                    'stock_quantity',
                    'is_active',
                ]
            ]);
    }

    public function test_can_update_item()
    {
        $item = Item::factory()->create();

        $updateData = [
            'name' => 'Updated Item Name',
            'description' => 'Updated description',
            'price' => 99.99,
            'category' => 'Updated Category',
            'stock_quantity' => 75,
            'is_active' => false,
        ];

        $response = $this->putJson("/api/admin/items/{$item->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Updated Item Name',
            'price' => 99.99,
            'is_active' => false,
        ]);
    }

    public function test_update_item_validation_fails_with_invalid_data()
    {
        $item = Item::factory()->create();

        $response = $this->putJson("/api/admin/items/{$item->id}", [
            'name' => '',
            'price' => 'invalid',
            'stock_quantity' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price', 'category', 'stock_quantity']);
    }

    public function test_can_soft_delete_item()
    {
        $item = Item::factory()->create();

        $response = $this->deleteJson("/api/admin/items/{$item->id}");

        $response->assertStatus(200);

        // Item should be marked as inactive (soft delete)
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'is_active' => false,
        ]);
    }

    public function test_can_get_categories()
    {
        Item::factory()->create(['category' => 'Electronics']);
        Item::factory()->create(['category' => 'Books']);
        Item::factory()->create(['category' => 'Electronics']); // Duplicate

        $response = $this->getJson('/api/admin/items-categories');

        $response->assertStatus(200);
        $categories = $response->json('data');

        $this->assertCount(2, $categories);
        $this->assertContains('Electronics', $categories);
        $this->assertContains('Books', $categories);
    }

    public function test_can_update_stock()
    {
        $item = Item::factory()->create(['stock_quantity' => 50]);

        // Test set operation
        $response = $this->putJson("/api/admin/items/{$item->id}/stock", [
            'stock_quantity' => 100,
            'operation' => 'set',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(100, $item->fresh()->stock_quantity);

        // Test add operation
        $response = $this->putJson("/api/admin/items/{$item->id}/stock", [
            'stock_quantity' => 25,
            'operation' => 'add',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(125, $item->fresh()->stock_quantity);

        // Test subtract operation
        $response = $this->putJson("/api/admin/items/{$item->id}/stock", [
            'stock_quantity' => 25,
            'operation' => 'subtract',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(100, $item->fresh()->stock_quantity);
    }

    public function test_can_check_availability()
    {
        $item1 = Item::factory()->create(['stock_quantity' => 10, 'is_active' => true]);
        $item2 = Item::factory()->create(['stock_quantity' => 5, 'is_active' => true]);

        $response = $this->postJson('/api/admin/items/check-availability', [
            'items' => [
                ['id' => $item1->id, 'quantity' => 5],
                ['id' => $item2->id, 'quantity' => 3],
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'all_available',
                    'items' => [
                        '*' => [
                            'item_id',
                            'name',
                            'requested_quantity',
                            'available_quantity',
                            'is_available',
                            'is_active',
                        ]
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertTrue($data['all_available']);
    }

    public function test_can_get_low_stock_items()
    {
        Item::factory()->create(['stock_quantity' => 5, 'is_active' => true]);
        Item::factory()->create(['stock_quantity' => 20, 'is_active' => true]);
        Item::factory()->create(['stock_quantity' => 8, 'is_active' => true]);

        $response = $this->getJson('/api/admin/items-low-stock');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data); // Only items with stock <= 10
    }

    public function test_requires_authentication()
    {
        // Clear authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/admin/items');

        $response->assertStatus(401);
    }
}
