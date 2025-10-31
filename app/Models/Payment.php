<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'amount',
        'payment_method',
        'notes',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order that owns the payment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($payment) {
            // Update order payment status after payment is created
            $payment->updateOrderPaymentStatus();
        });

        static::updated(function ($payment) {
            // Update order payment status after payment is updated
            $payment->updateOrderPaymentStatus();
        });

        static::deleted(function ($payment) {
            // Update order payment status after payment is deleted
            $payment->updateOrderPaymentStatus();
        });
    }

    /**
     * Update the order's payment status based on total payments.
     */
    public function updateOrderPaymentStatus()
    {
        $order = $this->order;
        $totalPaid = $order->payments()->sum('amount');

        if ($totalPaid == 0) {
            $order->payment_status = 'unpaid';
        } elseif ($totalPaid >= $order->total_amount) {
            $order->payment_status = 'paid';
        } else {
            $order->payment_status = 'partially_paid';
        }

        $order->save();
    }

    /**
     * Scope to filter by payment method.
     */
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }
}
