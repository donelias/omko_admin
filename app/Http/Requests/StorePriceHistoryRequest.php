<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && has_permissions('create', 'properties');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'property_id' => 'required|exists:properties,id',
            'price' => 'required|numeric|min:0|max:999999999.99',
            'status' => 'required|string|in:listed,sold,rented,price_changed,delisted',
            'transaction_type' => 'required|string|in:sale,rental',
            'days_on_market' => 'nullable|integer|min:0|max:10000',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'property_id.required' => 'Property ID is required',
            'property_id.exists' => 'Property not found',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price must be greater than 0',
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status value',
            'transaction_type.required' => 'Transaction type is required',
            'transaction_type.in' => 'Transaction type must be sale or rental',
        ];
    }
}
