<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends BaseApiController
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'has_orders',
            'name',
            'email',
            'phone',
            'address',
            'min_orders',
            'max_orders',
            'min_spent',
            'max_spent',
            'payment_status',
            'order_status',
            'created_from',
            'created_to'
        ]);

        $sorting = $request->only(['sort_by', 'sort_order']);
        $perPage = $request->get('per_page', 15);

        $customers = $this->getFilteredCustomers($filters, $sorting, $perPage);

        return $this->paginatedResponse(
            CustomerResource::collection($customers),
            'Customers retrieved successfully'
        );
    }

    /**
     * Get filtered customers with advanced search capabilities.
     */
    private function getFilteredCustomers(array $filters, array $sorting, int $perPage)
    {
        $query = Customer::query();

        // Basic search across multiple fields
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Specific field filters
        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['email'])) {
            $query->where('email', 'like', "%{$filters['email']}%");
        }

        if (!empty($filters['phone'])) {
            $query->where('phone', 'like', "%{$filters['phone']}%");
        }

        if (!empty($filters['address'])) {
            $query->where('address', 'like', "%{$filters['address']}%");
        }

        // Date range filters
        if (!empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (!empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        // Order-related filters
        if (isset($filters['has_orders'])) {
            $hasOrders = filter_var($filters['has_orders'], FILTER_VALIDATE_BOOLEAN);
            if ($hasOrders) {
                $query->has('orders');
            } else {
                $query->doesntHave('orders');
            }
        }

        // Filter by order count range
        if (!empty($filters['min_orders']) || !empty($filters['max_orders'])) {
            $query->withCount('orders');
            if (!empty($filters['min_orders'])) {
                $query->having('orders_count', '>=', $filters['min_orders']);
            }
            if (!empty($filters['max_orders'])) {
                $query->having('orders_count', '<=', $filters['max_orders']);
            }
        }

        // Filter by total spent range
        if (!empty($filters['min_spent']) || !empty($filters['max_spent'])) {
            $query->withSum('orders', 'total_amount');
            if (!empty($filters['min_spent'])) {
                $query->having('orders_sum_total_amount', '>=', $filters['min_spent']);
            }
            if (!empty($filters['max_spent'])) {
                $query->having('orders_sum_total_amount', '<=', $filters['max_spent']);
            }
        }

        // Filter by payment status
        if (!empty($filters['payment_status'])) {
            $query->whereHas('orders', function ($q) use ($filters) {
                $q->where('payment_status', $filters['payment_status']);
            });
        }

        // Filter by order status
        if (!empty($filters['order_status'])) {
            $query->whereHas('orders', function ($q) use ($filters) {
                $q->where('status', $filters['order_status']);
            });
        }

        // Load relationships for statistics
        $query->withCount('orders')
            ->with(['orders' => function ($q) {
                $q->select('customer_id', 'total_amount', 'payment_status');
            }, 'orders.payments' => function ($q) {
                $q->select('order_id', 'amount');
            }]);

        // Apply sorting
        $sortBy = $sorting['sort_by'] ?? 'created_at';
        $sortOrder = $sorting['sort_order'] ?? 'desc';

        if (in_array($sortBy, ['name', 'email', 'phone', 'address', 'created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        } elseif ($sortBy === 'total_orders') {
            $query->withCount('orders')->orderBy('orders_count', $sortOrder);
        } elseif ($sortBy === 'total_spent') {
            $query->withSum('orders', 'total_amount')->orderBy('orders_sum_total_amount', $sortOrder);
        }

        return $query->paginate($perPage);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $customer = Customer::create($validated);

        return $this->createdResponse(
            new CustomerResource($customer),
            'Customer created successfully'
        );
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['orders.payments']);

        return $this->resourceResponse(
            new CustomerResource($customer),
            'Customer retrieved successfully'
        );
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $validated = $request->validated();

        $customer->update($validated);

        return $this->resourceResponse(
            new CustomerResource($customer),
            'Customer updated successfully'
        );
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        // Check if customer has orders
        if ($customer->orders()->exists()) {
            return $this->errorResponse(
                'Cannot delete customer with existing orders. Use soft delete instead.',
                400
            );
        }

        $customer->forceDelete(); // Hard delete

        return $this->deletedResponse('Customer deleted successfully');
    }

    /**
     * Soft delete the specified customer.
     */
    public function softDelete(Customer $customer): JsonResponse
    {
        $customer->delete(); // This will soft delete due to SoftDeletes trait

        return $this->deletedResponse('Customer soft deleted successfully');
    }

    /**
     * Get customer statistics.
     */
    public function statistics(Customer $customer): JsonResponse
    {
        $customer->load(['orders.payments']);

        $stats = [
            'total_orders' => $customer->orders->count(),
            'completed_orders' => $customer->orders->where('status', 'completed')->count(),
            'pending_orders' => $customer->orders->where('status', 'pending')->count(),
            'cancelled_orders' => $customer->orders->where('status', 'cancelled')->count(),
            'total_spent' => $customer->orders->sum('total_amount'),
            'total_paid' => $customer->orders->sum(function ($order) {
                return $order->payments->sum('amount');
            }),
            'outstanding_balance' => $customer->orders->sum('total_amount') - $customer->orders->sum(function ($order) {
                return $order->payments->sum('amount');
            }),
            'payment_status_breakdown' => [
                'paid' => $customer->orders->where('payment_status', 'paid')->count(),
                'partially_paid' => $customer->orders->where('payment_status', 'partially_paid')->count(),
                'unpaid' => $customer->orders->where('payment_status', 'unpaid')->count(),
            ],
            'average_order_value' => $customer->orders->count() > 0
                ? $customer->orders->avg('total_amount')
                : 0,
            'first_order_date' => $customer->orders->min('created_at'),
            'last_order_date' => $customer->orders->max('created_at'),
        ];

        return $this->successResponse($stats, 'Customer statistics retrieved successfully');
    }

    /**
     * Search customers with quick results.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 10);

        if (empty($query)) {
            return $this->successResponse([], 'No search query provided');
        }

        $customers = Customer::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%");
        })
            ->withCount('orders')
            ->limit($limit)
            ->get();

        return $this->collectionResponse(
            CustomerResource::collection($customers),
            'Customer search results retrieved successfully'
        );
    }
}
