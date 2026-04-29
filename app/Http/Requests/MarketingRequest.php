<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketingRequest extends FormRequest
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
                Rule::unique('marketings')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($this->marketing?->id),
            ],
            'address' => ['sometimes', 'nullable', 'string'],
            'phone'   => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('marketings.validation.name_required'),
            'name.unique'   => __('marketings.validation.name_unique'),
            'phone.max'     => __('marketings.validation.phone_max'),
        ];
    }
}