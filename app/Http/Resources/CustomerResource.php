<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CustomerResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'total_orders' => $this->whenLoaded('orders', function () {
                return $this->orders->count();
            }),
            'total_spent' => $this->whenLoaded('orders', function () {
                return $this->orders->sum('total_amount');
            }),
            'total_paid' => $this->whenLoaded('orders', function () {
                return $this->orders->sum(function ($order) {
                    return $order->payments->sum('amount');
                });
            }),
            'outstanding_balance' => $this->whenLoaded('orders', function () {
                $totalSpent = $this->orders->sum('total_amount');
                $totalPaid = $this->orders->sum(function ($order) {
                    return $order->payments->sum('amount');
                });
                return $totalSpent - $totalPaid;
            }),
            'payment_status' => $this->whenLoaded('orders', function () {
                $paidOrders = $this->orders->where('payment_status', 'paid')->count();
                $partiallyPaidOrders = $this->orders->where('payment_status', 'partially_paid')->count();
                $unpaidOrders = $this->orders->where('payment_status', 'unpaid')->count();

                return [
                    'paid' => $paidOrders,
                    'partially_paid' => $partiallyPaidOrders,
                    'unpaid' => $unpaidOrders,
                ];
            }),
            'last_order_date' => $this->whenLoaded('orders', function () {
                return $this->orders->max('created_at');
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
