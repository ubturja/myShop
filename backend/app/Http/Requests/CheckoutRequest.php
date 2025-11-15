<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for checkout process.
 * 
 * Validates:
 * - fulfillment_type: Must be 'quick_local', 'standard', or 'group_buy'
 * - latitude/longitude: Required for quick_local delivery
 * - shipping_address: Required for standard delivery
 * - group_buy_id: Required for group_buy checkout
 * - payment_method: Payment method selection
 */
class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can checkout
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
            'fulfillment_type' => [
                'required',
                'string',
                'in:quick_local,standard,group_buy',
            ],
            
            // Quick local delivery coordinates
            'latitude' => [
                'required_if:fulfillment_type,quick_local',
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required_if:fulfillment_type,quick_local',
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            
            // Standard shipping address
            'shipping_address' => [
                'required_if:fulfillment_type,standard',
                'nullable',
                'array',
            ],
            'shipping_address.line1' => [
                'required_with:shipping_address',
                'string',
                'max:255',
            ],
            'shipping_address.line2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'shipping_address.city' => [
                'required_with:shipping_address',
                'string',
                'max:100',
            ],
            'shipping_address.state' => [
                'nullable',
                'string',
                'max:100',
            ],
            'shipping_address.postal_code' => [
                'required_with:shipping_address',
                'string',
                'max:20',
            ],
            'shipping_address.country' => [
                'required_with:shipping_address',
                'string',
                'max:2',
            ],
            
            // Group buy checkout
            'group_buy_id' => [
                'required_if:fulfillment_type,group_buy',
                'nullable',
                'integer',
                'exists:group_buys,id',
            ],
            
            // Payment
            'payment_method' => [
                'required',
                'string',
                'in:credit_card,crypto,wallet',
            ],
            'payment_token' => [
                'nullable',
                'string',
            ],
            
            // Optional notes
            'notes' => [
                'nullable',
                'string',
                'max:1000',
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
            'fulfillment_type.required' => 'Please select a delivery method',
            'fulfillment_type.in' => 'Invalid delivery method selected',
            'latitude.required_if' => 'Location coordinates are required for quick local delivery',
            'longitude.required_if' => 'Location coordinates are required for quick local delivery',
            'shipping_address.required_if' => 'Shipping address is required for standard delivery',
            'group_buy_id.required_if' => 'Group buy ID is required for group buy checkout',
            'group_buy_id.exists' => 'The selected group buy does not exist',
            'payment_method.required' => 'Please select a payment method',
            'payment_method.in' => 'Invalid payment method selected',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shipping_address.line1' => 'address line 1',
            'shipping_address.line2' => 'address line 2',
            'shipping_address.city' => 'city',
            'shipping_address.state' => 'state',
            'shipping_address.postal_code' => 'postal code',
            'shipping_address.country' => 'country',
        ];
    }
}
