<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentService
{
    /**
     * Create a new payment for an order.
     */
    public function createPayment(Order $order, array $data): Payment
    {
        return DB::transaction(function () use ($order, $data) {
            // Validate payment amount
            $this->validatePaymentAmount($order, $data['amount']);

            // Create the payment
            $payment = new Payment([
                'order_id' => $order->id,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
                'payment_date' => isset($data['payment_date'])
                    ? Carbon::parse($data['payment_date'])
                    : now(),
            ]);

            $payment->save();

            // The payment status update is handled automatically by the Payment model's boot method

            return $payment->fresh();
        });
    }

    /**
     * Update an existing payment.
     */
    public function updatePayment(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $order = $payment->order;

            // If amount is being changed, validate the new amount
            if (isset($data['amount']) && $data['amount'] != $payment->amount) {
                $this->validatePaymentAmountForUpdate($order, $payment, $data['amount']);
            }

            // Update payment fields
            $payment->fill([
                'amount' => $data['amount'] ?? $payment->amount,
                'payment_method' => $data['payment_method'] ?? $payment->payment_method,
                'notes' => $data['notes'] ?? $payment->notes,
                'payment_date' => isset($data['payment_date'])
                    ? Carbon::parse($data['payment_date'])
                    : $payment->payment_date,
            ]);

            $payment->save();

            // The payment status update is handled automatically by the Payment model's boot method

            return $payment->fresh();
        });
    }

    /**
     * Delete a payment.
     */
    public function deletePayment(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            // The payment status update is handled automatically by the Payment model's boot method
            return $payment->delete();
        });
    }

    /**
     * Get payment summary for an order.
     */
    public function getPaymentSummary(Order $order): array
    {
        $payments = $order->payments;
        $totalPaid = $payments->sum('amount');
        $remainingAmount = max(0, $order->total_amount - $totalPaid);
        $overpayment = max(0, $totalPaid - $order->total_amount);

        $paymentsByMethod = $payments->groupBy('payment_method')->map(function ($payments) {
            return [
                'count' => $payments->count(),
                'total' => $payments->sum('amount'),
            ];
        });

        // Calculate current payment status based on payments
        $paymentStatus = $this->calculatePaymentStatus($order);

        return [
            'order_total' => $order->total_amount,
            'total_paid' => $totalPaid,
            'remaining_amount' => $remainingAmount,
            'overpayment' => $overpayment,
            'payment_status' => $paymentStatus,
            'payment_count' => $payments->count(),
            'payments_by_method' => $paymentsByMethod,
            'latest_payment_date' => $payments->max('payment_date'),
            'first_payment_date' => $payments->min('payment_date'),
        ];
    }

    /**
     * Calculate payment status based on total payments.
     */
    public function calculatePaymentStatus(Order $order): string
    {
        $totalPaid = $order->payments()->sum('amount');

        if ($totalPaid == 0) {
            return 'unpaid';
        } elseif ($totalPaid >= $order->total_amount) {
            return 'paid';
        } else {
            return 'partially_paid';
        }
    }

    /**
     * Validate payment amount for new payment.
     */
    protected function validatePaymentAmount(Order $order, float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $totalPaid = $order->payments()->sum('amount');
        $newTotal = $totalPaid + $amount;

        // Allow overpayment but warn about it
        if ($newTotal > $order->total_amount) {
            // This is just a business rule - we allow overpayment but could log it
            \Log::info("Overpayment detected for order {$order->id}. Total: {$order->total_amount}, New total paid: {$newTotal}");
        }
    }

    /**
     * Validate payment amount for payment update.
     */
    protected function validatePaymentAmountForUpdate(Order $order, Payment $payment, float $newAmount): void
    {
        if ($newAmount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $totalPaidExcludingCurrent = $order->payments()
            ->where('id', '!=', $payment->id)
            ->sum('amount');

        $newTotal = $totalPaidExcludingCurrent + $newAmount;

        // Allow overpayment but warn about it
        if ($newTotal > $order->total_amount) {
            \Log::info("Overpayment detected for order {$order->id} after payment update. Total: {$order->total_amount}, New total paid: {$newTotal}");
        }
    }

    /**
     * Get payment statistics for reporting.
     */
    public function getPaymentStatistics(array $filters = []): array
    {
        $query = Payment::query();

        // Apply date filters if provided
        if (isset($filters['start_date'])) {
            $query->where('payment_date', '>=', Carbon::parse($filters['start_date']));
        }

        if (isset($filters['end_date'])) {
            $query->where('payment_date', '<=', Carbon::parse($filters['end_date']));
        }

        // Apply payment method filter if provided
        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        $payments = $query->get();

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'average_payment' => $payments->count() > 0 ? $payments->avg('amount') : 0,
            'payments_by_method' => $payments->groupBy('payment_method')->map(function ($payments) {
                return [
                    'count' => $payments->count(),
                    'total' => $payments->sum('amount'),
                    'average' => $payments->avg('amount'),
                ];
            }),
        ];
    }
}
