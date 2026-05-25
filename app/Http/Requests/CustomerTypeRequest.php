<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'     => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('customer_types')
                    ->where('company_id', $this->user()->company_id)
                    ->withoutTrashed()
                    ->ignore($this->customer_type?->id),
            ],
            'discount' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'    => __('customer_types.validation.type_required'),
            'type.unique'      => __('customer_types.validation.type_unique'),
            'type.max'         => __('customer_types.validation.type_max'),
            'discount.numeric' => __('customer_types.validation.discount_numeric'),
            'discount.min'     => __('customer_types.validation.discount_min'),
            'discount.max'     => __('customer_types.validation.discount_max'),
        ];
    }
}