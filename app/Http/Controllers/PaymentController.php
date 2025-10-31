<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends BaseApiController
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Display a listing of payments for a specific order.
     */
    public function index(Request $request, Order $order): JsonResponse
    {
        $payments = $order->payments()
            ->orderBy('payment_date', 'desc')
            ->get();

        return $this->successResponse(
            PaymentResource::collection($payments),
            'Payments retrieved successfully'
        );
    }

    /**
     * Store a newly created payment.
     */
    public function store(StorePaymentRequest $request, Order $order): JsonResponse
    {
        try {
            $payment = $this->paymentService->createPayment($order, $request->validated());

            return $this->successResponse(
                new PaymentResource($payment),
                'Payment recorded successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(Order $order, Payment $payment): JsonResponse
    {
        // Ensure payment belongs to the order
        if ($payment->order_id !== $order->id) {
            return $this->errorResponse('Payment not found for this order', 404);
        }

        return $this->successResponse(
            new PaymentResource($payment),
            'Payment retrieved successfully'
        );
    }

    /**
     * Update the specified payment.
     */
    public function update(UpdatePaymentRequest $request, Order $order, Payment $payment): JsonResponse
    {
        // Ensure payment belongs to the order
        if ($payment->order_id !== $order->id) {
            return $this->errorResponse('Payment not found for this order', 404);
        }

        try {
            $updatedPayment = $this->paymentService->updatePayment($payment, $request->validated());

            return $this->successResponse(
                new PaymentResource($updatedPayment),
                'Payment updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Order $order, Payment $payment): JsonResponse
    {
        // Ensure payment belongs to the order
        if ($payment->order_id !== $order->id) {
            return $this->errorResponse('Payment not found for this order', 404);
        }

        try {
            $this->paymentService->deletePayment($payment);

            return $this->successResponse(
                null,
                'Payment deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Get payment summary for an order.
     */
    public function summary(Order $order): JsonResponse
    {
        $summary = $this->paymentService->getPaymentSummary($order);

        return $this->successResponse(
            $summary,
            'Payment summary retrieved successfully'
        );
    }
}
