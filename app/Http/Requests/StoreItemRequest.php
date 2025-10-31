<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:999999.99',
            'category' => 'required|string|max:100',
            'stock_quantity' => 'required|integer|min:0|max:999999',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The item name is required.',
            'name.max' => 'The item name cannot exceed 255 characters.',
            'price.required' => 'The item price is required.',
            'price.numeric' => 'The price must be a valid number.',
            'price.min' => 'The price cannot be negative.',
            'price.max' => 'The price cannot exceed 999,999.99.',
            'category.required' => 'The item category is required.',
            'category.max' => 'The category name cannot exceed 100 characters.',
            'stock_quantity.required' => 'The stock quantity is required.',
            'stock_quantity.integer' => 'The stock quantity must be a whole number.',
            'stock_quantity.min' => 'The stock quantity cannot be negative.',
            'stock_quantity.max' => 'The stock quantity cannot exceed 999,999.',
            'description.max' => 'The description cannot exceed 1,000 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure is_active defaults to true if not provided
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }

        // Clean up category name
        if ($this->has('category')) {
            $this->merge([
                'category' => trim($this->category)
            ]);
        }
    }
}
