<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('customers')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($this->customer?->id),
            ],
            'address'          => ['sometimes', 'nullable', 'string'],
            'phone'            => ['sometimes', 'nullable', 'string', 'max:20'],
            'customer_type_id' => ['sometimes', 'nullable', 'integer', 'exists:customer_types,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => __('customers.validation.name_required'),
            'name.unique'             => __('customers.validation.name_unique'),
            'phone.max'               => __('customers.validation.phone_max'),
            'customer_type_id.exists' => __('customers.validation.customer_type_not_found'),
        ];
    }
}