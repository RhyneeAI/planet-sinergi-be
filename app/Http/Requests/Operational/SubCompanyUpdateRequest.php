<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubCompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'mandor_uuid' => [
                'sometimes',
                'string',
                Rule::exists('users', 'uuid')
                    ->where('company_id', $companyId)
                    ->where('role', 'MANDOR')
                    ->where('is_active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => __('operational.validation.name_string'),
            'name.max' => __('operational.validation.sub_company_name_max'),
            'mandor_uuid.exists' => __('operational.validation.mandor_uuid_not_found'),
        ];
    }
}
