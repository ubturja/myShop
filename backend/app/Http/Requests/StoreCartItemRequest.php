<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for adding items to shopping cart.
 * 
 * Validates:
 * - product_id: Must be valid existing product
 * - quantity: Must be positive integer (1-999)
 * - store_id: Optional, required if product is quick_item
 */
class StoreCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can add to cart
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:999',
            ],
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Product ID is required',
            'product_id.exists' => 'The selected product does not exist',
            'quantity.required' => 'Quantity is required',
            'quantity.min' => 'Quantity must be at least 1',
            'quantity.max' => 'Quantity cannot exceed 999',
            'store_id.exists' => 'The selected store does not exist',
        ];
    }

    /**
     * Configure the validator instance.
     * Add custom validation logic for quick items requiring store_id.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if product is quick item and requires store_id
            if ($this->product_id) {
                $product = \App\Models\Product::find($this->product_id);
                
                if ($product && $product->is_quick_item && !$this->store_id) {
                    $validator->errors()->add(
                        'store_id',
                        'Store selection is required for quick commerce items'
                    );
                }
            }
        });
    }
}
