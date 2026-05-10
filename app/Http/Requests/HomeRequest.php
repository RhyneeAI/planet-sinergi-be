<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => [
                'nullable',
                'string',
                Rule::in(['day', 'month', 'year']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' => __('home.validation.period_invalid'),
        ];
    }
}
