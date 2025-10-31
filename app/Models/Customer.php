<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the orders for the customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the total number of orders for the customer.
     */
    public function getTotalOrdersAttribute(): int
    {
        return $this->orders()->count();
    }

    /**
     * Get the total amount spent by the customer.
     */
    public function getTotalSpentAttribute(): float
    {
        return $this->orders()->sum('total_amount');
    }

    /**
     * Get the total paid amount by the customer.
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->orders()
            ->whereHas('payments')
            ->with('payments')
            ->get()
            ->sum(function ($order) {
                return $order->payments->sum('amount');
            });
    }
}
