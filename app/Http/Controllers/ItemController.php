<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends BaseApiController
{
    /**
     * Display a listing of items.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'name',
            'description',
            'category',
            'is_active',
            'low_stock',
            'min_price',
            'max_price',
            'min_stock',
            'max_stock',
            'stock_threshold',
            'created_from',
            'created_to'
        ]);

        $sorting = $request->only(['sort_by', 'sort_order']);
        $perPage = $request->get('per_page', 15);

        $items = $this->getFilteredItems($filters, $sorting, $perPage);

        return $this->paginatedResponse(
            ItemResource::collection($items),
            'Items retrieved successfully'
        );
    }

    /**
     * Get filtered items with advanced search capabilities.
     */
    private function getFilteredItems(array $filters, array $sorting, int $perPage)
    {
        $query = Item::query();

        // Basic search across multiple fields
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Specific field filters
        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['description'])) {
            $query->where('description', 'like', "%{$filters['description']}%");
        }

        if (!empty($filters['category'])) {
            $query->where('category', 'like', "%{$filters['category']}%");
        }

        // Active status filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Price range filters
        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Stock quantity filters
        if (!empty($filters['min_stock'])) {
            $query->where('stock_quantity', '>=', $filters['min_stock']);
        }

        if (!empty($filters['max_stock'])) {
            $query->where('stock_quantity', '<=', $filters['max_stock']);
        }

        // Low stock filter with custom threshold
        if (filter_var($filters['low_stock'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $threshold = $filters['stock_threshold'] ?? 10;
            $query->where('stock_quantity', '<=', $threshold);
        }

        // Date range filters
        if (!empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (!empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        // Apply sorting
        $sortBy = $sorting['sort_by'] ?? 'created_at';
        $sortOrder = $sorting['sort_order'] ?? 'desc';

        $allowedSortFields = ['name', 'price', 'category', 'stock_quantity', 'is_active', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($perPage);
    }

    /**
     * Store a newly created item.
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $item = Item::create($validated);

        return $this->createdResponse(
            new ItemResource($item),
            'Item created successfully'
        );
    }

    /**
     * Display the specified item.
     */
    public function show(Item $item): JsonResponse
    {
        return $this->resourceResponse(
            new ItemResource($item),
            'Item retrieved successfully'
        );
    }

    /**
     * Update the specified item.
     */
    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $validated = $request->validated();
        $item->update($validated);

        return $this->resourceResponse(
            new ItemResource($item),
            'Item updated successfully'
        );
    }

    /**
     * Remove the specified item.
     */
    public function destroy(Item $item): JsonResponse
    {
        // Soft delete - just mark as inactive
        $item->update(['is_active' => false]);

        return $this->deletedResponse('Item deactivated successfully');
    }

    /**
     * Get unique categories for dropdown.
     */
    public function categories(): JsonResponse
    {
        $categories = Item::distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->pluck('category')
            ->sort()
            ->values();

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    /**
     * Update stock quantity for an item.
     */
    public function updateStock(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'stock_quantity' => 'required|integer|min:0|max:999999',
            'operation' => 'in:set,add,subtract',
        ]);

        $operation = $validated['operation'] ?? 'set';
        $quantity = $validated['stock_quantity'];

        switch ($operation) {
            case 'add':
                $item->stock_quantity += $quantity;
                break;
            case 'subtract':
                $item->stock_quantity = max(0, $item->stock_quantity - $quantity);
                break;
            default:
                $item->stock_quantity = $quantity;
                break;
        }

        $item->save();

        return $this->resourceResponse(
            new ItemResource($item),
            'Stock updated successfully'
        );
    }

    /**
     * Check availability of items for order creation.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $availability = [];
        $allAvailable = true;

        foreach ($validated['items'] as $requestedItem) {
            $item = Item::find($requestedItem['id']);
            $isAvailable = $item->is_active && $item->stock_quantity >= $requestedItem['quantity'];

            $availability[] = [
                'item_id' => $item->id,
                'name' => $item->name,
                'requested_quantity' => $requestedItem['quantity'],
                'available_quantity' => $item->stock_quantity,
                'is_available' => $isAvailable,
                'is_active' => $item->is_active,
            ];

            if (!$isAvailable) {
                $allAvailable = false;
            }
        }

        return $this->successResponse([
            'all_available' => $allAvailable,
            'items' => $availability,
        ], 'Availability check completed');
    }

    /**
     * Get low stock items.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $threshold = $request->get('threshold', 10);

        $items = Item::where('is_active', true)
            ->where('stock_quantity', '<=', $threshold)
            ->orderBy('stock_quantity', 'asc')
            ->get();

        return $this->collectionResponse(
            ItemResource::collection($items),
            'Low stock items retrieved successfully'
        );
    }

    /**
     * Search items with quick results.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 10);

        if (empty($query)) {
            return $this->successResponse([], 'No search query provided');
        }

        $items = Item::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhere('category', 'like', "%{$query}%");
        })
            ->where('is_active', true)
            ->limit($limit)
            ->get();

        return $this->collectionResponse(
            ItemResource::collection($items),
            'Item search results retrieved successfully'
        );
    }
}
