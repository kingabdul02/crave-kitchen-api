<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Item;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    /**
     * Get filtered orders with pagination.
     */
    public function getFilteredOrders(array $filters, array $sorting = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::with(['customer', 'orderItems.item', 'payments']);

        // Apply basic filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (!empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        // Amount range filters
        if (!empty($filters['min_amount'])) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }

        // Discount filter
        if (isset($filters['has_discount'])) {
            $hasDiscount = filter_var($filters['has_discount'], FILTER_VALIDATE_BOOLEAN);
            if ($hasDiscount) {
                $query->where(function (Builder $q) {
                    $q->whereNotNull('discount_type')
                        ->where('discount_value', '>', 0);
                });
            } else {
                $query->where(function (Builder $q) {
                    $q->whereNull('discount_type')
                        ->orWhere('discount_value', '<=', 0);
                });
            }
        }

        // Search filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('orderItems.item', function (Builder $itemQuery) use ($search) {
                        $itemQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%");
                    });
            });
        }

        // Specific field searches
        if (!empty($filters['order_number'])) {
            $query->where('order_number', 'like', "%{$filters['order_number']}%");
        }

        if (!empty($filters['customer_name'])) {
            $query->whereHas('customer', function (Builder $customerQuery) use ($filters) {
                $customerQuery->where('name', 'like', "%{$filters['customer_name']}%");
            });
        }

        if (!empty($filters['customer_email'])) {
            $query->whereHas('customer', function (Builder $customerQuery) use ($filters) {
                $customerQuery->where('email', 'like', "%{$filters['customer_email']}%");
            });
        }

        if (!empty($filters['item_name'])) {
            $query->whereHas('orderItems.item', function (Builder $itemQuery) use ($filters) {
                $itemQuery->where('name', 'like', "%{$filters['item_name']}%");
            });
        }

        // Apply sorting
        $sortBy = $sorting['sort_by'] ?? 'created_at';
        $sortOrder = $sorting['sort_order'] ?? 'desc';

        $allowedSortFields = [
            'order_number',
            'status',
            'payment_status',
            'total_amount',
            'subtotal',
            'created_at',
            'updated_at'
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } elseif ($sortBy === 'customer_name') {
            $query->join('customers', 'orders.customer_id', '=', 'customers.id')
                ->orderBy('customers.name', $sortOrder)
                ->select('orders.*');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new order with items.
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // Validate customer exists
            $customer = Customer::findOrFail($data['customer_id']);

            // Check item availability
            $this->validateItemsAvailability($data['items']);

            // Calculate totals
            $subtotal = $this->calculateSubtotal($data['items']);
            $discountAmount = $this->calculateDiscountAmount(
                $subtotal,
                $data['discount_type'] ?? null,
                $data['discount_value'] ?? 0
            );
            $totalAmount = $subtotal - $discountAmount;

            // Create order
            $order = Order::create([
                'customer_id' => $data['customer_id'],
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? 0,
                'total_amount' => $totalAmount,
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            // Create order items
            foreach ($data['items'] as $itemData) {
                $this->createOrderItem($order, $itemData);
            }

            // Update item stock quantities
            $this->updateItemStock($data['items']);

            return $order->load(['customer', 'orderItems.item', 'payments']);
        });
    }

    /**
     * Update an existing order.
     */
    public function updateOrder(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            // If items are being updated, validate availability
            if (isset($data['items'])) {
                // Restore original stock quantities
                $this->restoreItemStock($order->orderItems);

                // Validate new items availability
                $this->validateItemsAvailability($data['items']);

                // Delete existing order items
                $order->orderItems()->delete();
            }

            // Update order basic info
            $updateData = [];
            if (isset($data['customer_id'])) {
                Customer::findOrFail($data['customer_id']); // Validate customer exists
                $updateData['customer_id'] = $data['customer_id'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            // Recalculate totals if items or discount changed
            if (isset($data['items']) || isset($data['discount_type']) || isset($data['discount_value'])) {
                $items = $data['items'] ?? $this->getOrderItemsData($order);
                $subtotal = $this->calculateSubtotal($items);
                $discountAmount = $this->calculateDiscountAmount(
                    $subtotal,
                    $data['discount_type'] ?? $order->discount_type,
                    $data['discount_value'] ?? $order->discount_value
                );
                $totalAmount = $subtotal - $discountAmount;

                $updateData['subtotal'] = $subtotal;
                $updateData['discount_type'] = $data['discount_type'] ?? $order->discount_type;
                $updateData['discount_value'] = $data['discount_value'] ?? $order->discount_value;
                $updateData['total_amount'] = $totalAmount;

                // Create new order items if provided
                if (isset($data['items'])) {
                    foreach ($data['items'] as $itemData) {
                        $this->createOrderItem($order, $itemData);
                    }

                    // Update item stock quantities
                    $this->updateItemStock($data['items']);
                }
            }

            $order->update($updateData);

            return $order->load(['customer', 'orderItems.item', 'payments']);
        });
    }

    /**
     * Delete an order.
     */
    public function deleteOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Restore item stock quantities
            $this->restoreItemStock($order->orderItems);

            // Delete order (cascade will handle order items and payments)
            $order->delete();
        });
    }

    /**
     * Update order status.
     */
    public function updateOrderStatus(Order $order, string $status): Order
    {
        $order->update(['status' => $status]);
        return $order->load(['customer', 'orderItems.item', 'payments']);
    }

    /**
     * Update payment status.
     */
    public function updatePaymentStatus(Order $order, string $paymentStatus): Order
    {
        $order->update(['payment_status' => $paymentStatus]);
        return $order->load(['customer', 'orderItems.item', 'payments']);
    }

    /**
     * Get order summary statistics.
     */
    public function getOrderSummary(): array
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $processedOrders = Order::where('status', 'processed')->count();
        $completedOrders = Order::where('status', 'completed')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        $totalRevenue = Order::whereIn('status', ['completed', 'processed'])
            ->sum('total_amount');

        $unpaidAmount = Order::where('payment_status', 'unpaid')
            ->whereIn('status', ['pending', 'processed', 'completed'])
            ->sum('total_amount');

        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'processed_orders' => $processedOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $cancelledOrders,
            'total_revenue' => $totalRevenue,
            'unpaid_amount' => $unpaidAmount,
        ];
    }

    /**
     * Validate that all items are available in requested quantities.
     */
    private function validateItemsAvailability(array $items): void
    {
        foreach ($items as $itemData) {
            $item = Item::findOrFail($itemData['item_id']);

            if (!$item->is_active) {
                throw new Exception("Item '{$item->name}' is not active and cannot be ordered.");
            }

            if ($item->stock_quantity < $itemData['quantity']) {
                throw new Exception(
                    "Insufficient stock for item '{$item->name}'. " .
                        "Requested: {$itemData['quantity']}, Available: {$item->stock_quantity}"
                );
            }
        }
    }

    /**
     * Calculate subtotal from items.
     */
    private function calculateSubtotal(array $items): float
    {
        $subtotal = 0;
        foreach ($items as $itemData) {
            $subtotal += $itemData['quantity'] * $itemData['unit_price'];
        }
        return $subtotal;
    }

    /**
     * Calculate discount amount.
     */
    private function calculateDiscountAmount(float $subtotal, ?string $discountType, float $discountValue): float
    {
        if (!$discountType || $discountValue <= 0) {
            return 0;
        }

        if ($discountType === 'percentage') {
            return $subtotal * ($discountValue / 100);
        }

        if ($discountType === 'fixed') {
            return min($discountValue, $subtotal); // Don't allow discount to exceed subtotal
        }

        return 0;
    }

    /**
     * Create an order item.
     */
    private function createOrderItem(Order $order, array $itemData): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $itemData['item_id'],
            'quantity' => $itemData['quantity'],
            'unit_price' => $itemData['unit_price'],
            'total_price' => $itemData['quantity'] * $itemData['unit_price'],
        ]);
    }

    /**
     * Update item stock quantities after order creation.
     */
    private function updateItemStock(array $items): void
    {
        foreach ($items as $itemData) {
            $item = Item::findOrFail($itemData['item_id']);
            $item->decrement('stock_quantity', $itemData['quantity']);
        }
    }

    /**
     * Restore item stock quantities (used when updating/deleting orders).
     */
    private function restoreItemStock($orderItems): void
    {
        foreach ($orderItems as $orderItem) {
            $item = Item::find($orderItem->item_id);
            if ($item) {
                $item->increment('stock_quantity', $orderItem->quantity);
            }
        }
    }

    /**
     * Get order items data from existing order.
     */
    private function getOrderItemsData(Order $order): array
    {
        return $order->orderItems->map(function ($orderItem) {
            return [
                'item_id' => $orderItem->item_id,
                'quantity' => $orderItem->quantity,
                'unit_price' => $orderItem->unit_price,
            ];
        })->toArray();
    }
}
