<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('pos_suppliers')
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
            'name.required' => __('pos.suppliers.validation.name_required'),
            'name.unique'   => __('pos.suppliers.validation.name_unique'),
            'phone.regex'   => __('pos.suppliers.validation.phone_numeric'),
            'phone.max'     => __('pos.suppliers.validation.phone_max'),
        ];
    }
}
