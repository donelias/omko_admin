<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientScreeningRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'property_id' => 'required|integer|exists:propertys,id',
            'customer_name' => 'required|string|max:191',
            'customer_email' => 'nullable|email|max:191',
            'customer_phone' => 'nullable|string|max:191',
            'origin' => 'sometimes|string|max:40',
            'answers' => 'required|array|min:1',
            'answers.*' => 'nullable|string|max:5000',
        ];
    }

    public function messages()
    {
        return [
            'property_id.required' => 'La propiedad es requerida.',
            'property_id.exists' => 'La propiedad no existe.',
            'customer_name.required' => 'El nombre es requerido.',
            'customer_email.email' => 'El correo no es válido.',
            'answers.required' => 'Debe responder al menos una pregunta.',
        ];
    }
}