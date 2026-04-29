<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($this->supplier?->id),
            ],
            'address' => ['nullable', 'string'],
            'phone'   => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+\-\s\(\)0-9]+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('suppliers.validation.name_required'),
            'name.unique'   => __('suppliers.validation.name_unique'),
            'phone.regex'   => __('suppliers.validation.phone_numeric'),
            'phone.max'     => __('suppliers.validation.phone_max'),
        ];
    }
}