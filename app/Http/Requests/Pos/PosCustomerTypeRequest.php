<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCustomerTypeRequest extends FormRequest
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
                Rule::unique('pos_customer_types')
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
            'type.required'    => __('pos.customer_types.validation.type_required'),
            'type.unique'      => __('pos.customer_types.validation.type_unique'),
            'type.max'         => __('pos.customer_types.validation.type_max'),
            'discount.numeric' => __('pos.customer_types.validation.discount_numeric'),
            'discount.min'     => __('pos.customer_types.validation.discount_min'),
            'discount.max'     => __('pos.customer_types.validation.discount_max'),
        ];
    }
}
