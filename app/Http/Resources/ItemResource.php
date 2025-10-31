<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ItemResource extends BaseResource
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
            'description' => $this->description,
            'price' => (float) $this->price,
            'category' => $this->category,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
            'is_low_stock' => $this->stock_quantity <= 10, // Consider low stock threshold
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
