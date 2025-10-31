<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'order_id' => $this->order_id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->getPaymentMethodLabel(),
            'notes' => $this->notes,
            'payment_date' => $this->payment_date?->toISOString(),
            'payment_date_formatted' => $this->payment_date?->format('M j, Y g:i A'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Include order information when needed
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                    'total_amount' => (float) $this->order->total_amount,
                    'payment_status' => $this->order->payment_status,
                ];
            }),
        ];
    }

    /**
     * Get human-readable payment method label.
     */
    protected function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'transfer' => 'Bank Transfer',
            'pos' => 'POS/Card',
            'other' => 'Other',
            default => ucfirst($this->payment_method),
        };
    }
}
