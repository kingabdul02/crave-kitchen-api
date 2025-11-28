<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends BaseApiController
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Clear all order list caches.
     */
    protected function clearOrderCaches(): void
    {
        // Clear the order cache timestamp to invalidate all order caches
        cache()->forget('orders_cache_timestamp');
        cache()->put('orders_cache_timestamp', now()->timestamp);
    }

    /**
     * Display a listing of orders with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status',
            'payment_status',
            'customer_id',
            'date_from',
            'date_to',
            'search',
            'min_amount',
            'max_amount',
            'order_number',
            'customer_name',
            'customer_email',
            'item_name',
            'has_discount',
            'created_from',
            'created_to'
        ]);

        $sorting = $request->only(['sort_by', 'sort_order']);
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Get cache timestamp for invalidation
        $cacheTimestamp = cache()->get('orders_cache_timestamp', 0);

        // Create cache key based on filters, sorting, pagination, and timestamp
        $cacheKey = 'orders_list_' . md5(serialize($filters) . serialize($sorting) . $perPage . $page . $cacheTimestamp);

        // Cache for 2 minutes for frequently accessed order lists
        $orders = cache()->remember($cacheKey, 120, function () use ($filters, $sorting, $perPage) {
            return $this->orderService->getFilteredOrders($filters, $sorting, $perPage);
        });

        return $this->paginatedResponse(
            OrderResource::collection($orders),
            'Orders retrieved successfully'
        );
    }

    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder($request->validated());

            // Invalidate order list cache
            $this->clearOrderCaches();

            return $this->createdResponse(
                new OrderResource($order),
                'Order created successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['customer', 'orderItems.item', 'payments']);

        return $this->successResponse(
            new OrderResource($order),
            'Order retrieved successfully'
        );
    }

    /**
     * Update the specified order.
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        try {
            $updatedOrder = $this->orderService->updateOrder($order, $request->validated());

            // Invalidate order list cache
            $this->clearOrderCaches();

            return $this->successResponse(
                new OrderResource($updatedOrder),
                'Order updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Remove the specified order.
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            $this->orderService->deleteOrder($order);

            // Invalidate order list cache
            $this->clearOrderCaches();

            return $this->successResponse(
                null,
                'Order deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Get order summary statistics.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->orderService->getOrderSummary();

        return $this->successResponse(
            $summary,
            'Order summary retrieved successfully'
        );
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,processed,completed,cancelled'
        ]);

        try {
            $updatedOrder = $this->orderService->updateOrderStatus($order, $request->status);

            // Invalidate order list cache
            $this->clearOrderCaches();

            return $this->successResponse(
                new OrderResource($updatedOrder),
                'Order status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Update payment status.
     */
    public function updatePaymentStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'payment_status' => 'required|in:unpaid,partially_paid,paid'
        ]);

        try {
            $updatedOrder = $this->orderService->updatePaymentStatus($order, $request->payment_status);

            // Invalidate order list cache
            $this->clearOrderCaches();

            return $this->successResponse(
                new OrderResource($updatedOrder),
                'Payment status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Update settlement status.
     */
    public function updateSettled(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'is_settled' => 'required|boolean'
        ]);

        try {
            $order->update([
                'is_settled' => $request->is_settled
            ]);

            // Invalidate cache
            $this->clearOrderCaches();

            return $this->successResponse(
                new OrderResource($order->fresh(['customer', 'orderItems.item', 'payments'])),
                'Settlement status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Search orders with quick results.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 10);

        if (empty($query)) {
            return $this->successResponse([], 'No search query provided');
        }

        $orders = Order::with(['customer', 'orderItems.item'])
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

        return $this->collectionResponse(
            OrderResource::collection($orders),
            'Order search results retrieved successfully'
        );
    }
}
