<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'property_id' => 'required|integer|exists:propertys,id',
            'price' => 'required|numeric|gt:0',
            'price_per_sqm' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:listed,sold,rented,price_changed,delisted',
            'transaction_type' => 'sometimes|in:sale,rental',
            'days_on_market' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}