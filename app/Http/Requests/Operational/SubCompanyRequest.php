<?php

namespace App\Http\Requests\Operational;

use App\Models\SubCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStore = $this->isMethod('POST');
        $companyId = $this->user()->company_id;

        $mandorId = null;
        if (!$isStore) {
            $subCompany = SubCompany::where('uuid', $this->route('uuid'))->first();
            $mandorId = $subCompany?->mandor_id;
        }

        $mandorRequired = $isStore ? 'required' : 'sometimes';
        $subCompanyRequired = $isStore ? 'required' : 'sometimes';

        return [
            'mandor' => [$mandorRequired, 'array'],
            'mandor.name' => [$mandorRequired, 'string', 'max:255'],
            'mandor.phone' => [
                $mandorRequired,
                'string',
                'min:10',
                'max:20',
                Rule::unique('users', 'phone')
                    ->where('company_id', $companyId)
                    ->ignore($mandorId),
            ],
            'mandor.email' => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('users', 'email')
                    ->where('company_id', $companyId)
                    ->ignore($mandorId),
            ],
            'mandor.address' => ['sometimes', 'nullable', 'string'],
            'mandor.is_active' => ['sometimes', 'boolean'],

            'sub_company' => [$subCompanyRequired, 'array'],
            'sub_company.name' => [$subCompanyRequired, 'string', 'max:255'],
            'sub_company.address' => ['sometimes', 'nullable', 'string'],
            'sub_company.latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'sub_company.longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'sub_company.radius_meter' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5000'],
            'sub_company.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'mandor.required' => __('operational.validation.mandor_payload_required'),
            'mandor.array' => __('operational.validation.mandor_payload_required'),
            'mandor.name.required' => __('operational.validation.name_required'),
            'mandor.name.string' => __('operational.validation.name_string'),
            'mandor.name.max' => __('operational.validation.name_max'),
            'mandor.phone.required' => __('operational.validation.phone_required'),
            'mandor.phone.min' => __('operational.validation.phone_min'),
            'mandor.phone.unique' => __('operational.validation.phone_unique'),
            'mandor.email.email' => __('operational.validation.email_invalid'),
            'mandor.email.unique' => __('operational.validation.email_unique'),
            'sub_company.required' => __('operational.validation.sub_company_payload_required'),
            'sub_company.array' => __('operational.validation.sub_company_payload_required'),
            'sub_company.name.required' => __('operational.validation.sub_company_name_required'),
            'sub_company.name.string' => __('operational.validation.name_string'),
            'sub_company.name.max' => __('operational.validation.sub_company_name_max'),
        ];
    }
}
