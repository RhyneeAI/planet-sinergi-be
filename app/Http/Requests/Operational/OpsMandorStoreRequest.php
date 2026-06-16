<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OpsMandorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->where('company_id', $companyId),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('users', 'email')->where('company_id', $companyId),
            ],
            'address' => ['sometimes', 'nullable', 'string'],
            'sub_company_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('ops_sub_companies', 'uuid')->where('company_id', $companyId),
            ],
            'sub_company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sub_company_code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasUuid = filled($this->input('sub_company_uuid'));
            $hasName = filled($this->input('sub_company_name'));

            if ($hasUuid && $hasName) {
                $validator->errors()->add('sub_company_uuid', __('operational.validation.sub_company_branch_xor'));
                $validator->errors()->add('sub_company_name', __('operational.validation.sub_company_branch_xor'));
            }

            if (!$hasUuid && !$hasName) {
                $validator->errors()->add('sub_company_uuid', __('operational.validation.sub_company_branch_required'));
                $validator->errors()->add('sub_company_name', __('operational.validation.sub_company_branch_required'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => __('operational.validation.name_required'),
            'phone.required' => __('operational.validation.phone_required'),
            'phone.unique' => __('operational.validation.phone_unique'),
            'email.email' => __('operational.validation.email_invalid'),
            'email.unique' => __('operational.validation.email_unique'),
            'sub_company_uuid.uuid' => __('operational.validation.sub_company_uuid_invalid'),
            'sub_company_uuid.exists' => __('operational.validation.sub_company_uuid_not_found'),
            'sub_company_name.max' => __('operational.validation.sub_company_name_max'),
            'sub_company_code.max' => __('operational.validation.sub_company_code_max'),
        ];
    }
}
