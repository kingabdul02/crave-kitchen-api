<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class OrderResource extends BaseResource
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
            'order_number' => $this->order_number,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'order_items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'total_amount' => (float) $this->total_amount,
            'payment_status' => $this->payment_status,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'total_paid' => $this->whenLoaded('payments', function () {
                return (float) $this->payments->sum('amount');
            }),
            'balance_due' => $this->whenLoaded('payments', function () {
                return (float) ($this->total_amount - $this->payments->sum('amount'));
            }),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
