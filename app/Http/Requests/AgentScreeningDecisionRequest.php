<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentScreeningDecisionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'decision' => 'required|in:aprobado,rechazado',
            'notas' => 'required_if:decision,rechazado|nullable|string|max:5000',
        ];
    }
}