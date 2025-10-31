<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // For now, all authenticated users can update payments
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/', // Ensure max 2 decimal places
            ],
            'payment_method' => [
                'sometimes',
                'required',
                'string',
                'in:cash,transfer,pos,other',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'payment_date' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:now',
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Payment amount must be a valid number.',
            'amount.min' => 'Payment amount must be at least 0.01.',
            'amount.max' => 'Payment amount cannot exceed 999,999.99.',
            'amount.regex' => 'Payment amount can have at most 2 decimal places.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Payment method must be one of: cash, transfer, POS, or other.',
            'notes.max' => 'Payment notes cannot exceed 1000 characters.',
            'payment_date.date' => 'Payment date must be a valid date.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'payment_method' => 'payment method',
            'payment_date' => 'payment date',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert payment_method to lowercase for consistency
        if ($this->has('payment_method')) {
            $this->merge([
                'payment_method' => strtolower($this->payment_method),
            ]);
        }

        // Handle empty payment_date
        if ($this->has('payment_date') && empty($this->payment_date)) {
            $this->merge([
                'payment_date' => null,
            ]);
        }
    }
}
