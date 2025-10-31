<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends BaseApiController
{
    /**
     * Perform global search across all entities.
     */
    public function globalSearch(Request $request): JsonResponse
    {
        $query = $request->get('query', '');
        $limit = $request->get('limit', 5);

        if (empty($query)) {
            return $this->successResponse([
                'customers' => [],
                'orders' => [],
                'items' => []
            ], 'No search query provided');
        }

        // Search customers
        $customers = Customer::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%");
        })
            ->withCount('orders')
            ->limit($limit)
            ->get();

        // Search orders
        $orders = Order::with(['customer'])
            ->where(function ($q) use ($query) {
                $q->where('order_number', 'like', "%{$query}%")
                    ->orWhere('notes', 'like', "%{$query}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($query) {
                        $customerQuery->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    });
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Search items
        $items = Item::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhere('category', 'like', "%{$query}%");
        })
            ->where('is_active', true)
            ->limit($limit)
            ->get();

        $results = [
            'customers' => CustomerResource::collection($customers),
            'orders' => OrderResource::collection($orders),
            'items' => ItemResource::collection($items),
            'total_results' => $customers->count() + $orders->count() + $items->count()
        ];

        return $this->successResponse($results, 'Global search results retrieved successfully');
    }

    /**
     * Get search suggestions based on query.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 10);

        if (strlen($query) < 2) {
            return $this->successResponse([], 'Query too short for suggestions');
        }

        $suggestions = [];

        // Customer name suggestions
        $customerNames = Customer::where('name', 'like', "%{$query}%")
            ->pluck('name')
            ->take($limit / 3)
            ->map(function ($name) {
                return ['type' => 'customer', 'value' => $name];
            });

        // Item name suggestions
        $itemNames = Item::where('name', 'like', "%{$query}%")
            ->where('is_active', true)
            ->pluck('name')
            ->take($limit / 3)
            ->map(function ($name) {
                return ['type' => 'item', 'value' => $name];
            });

        // Order number suggestions
        $orderNumbers = Order::where('order_number', 'like', "%{$query}%")
            ->pluck('order_number')
            ->take($limit / 3)
            ->map(function ($orderNumber) {
                return ['type' => 'order', 'value' => $orderNumber];
            });

        $suggestions = $customerNames->concat($itemNames)->concat($orderNumbers)->take($limit);

        return $this->successResponse($suggestions, 'Search suggestions retrieved successfully');
    }
}
