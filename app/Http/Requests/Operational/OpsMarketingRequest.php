<?php

namespace App\Http\Requests\Operational;

use App\Models\AbsJabatan;
use App\Models\AbsShift;
use App\Models\SubCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpsMarketingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->marketing?->id;
        $companyId = $this->user()->company_id;

        return [
            'name'    => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'phone'   => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')
                    ->where('company_id', $companyId)
                    ->ignore($userId),
            ],
            'email'   => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('users', 'email')
                    ->where('company_id', $companyId)
                    ->ignore($userId),
            ],
            'address' => ['sometimes', 'nullable', 'string'],
            'jabatan_uuid' => ['sometimes', 'nullable', 'uuid', function ($attribute, $value, $fail) use ($companyId) {
                if ($value && !AbsJabatan::where('uuid', $value)->where('company_id', $companyId)->exists()) {
                    $fail(__('absence.validation.jabatan_uuid_not_found'));
                }
            }],
            'sub_company_uuid' => ['sometimes', 'nullable', 'uuid', function ($attribute, $value, $fail) use ($companyId) {
                if ($value && !SubCompany::where('uuid', $value)->where('company_id', $companyId)->exists()) {
                    $fail(__('absence.validation.sub_company_uuid_not_found'));
                }
            }],
            'shift_uuid' => ['sometimes', 'nullable', 'uuid', function ($attribute, $value, $fail) use ($companyId) {
                if ($value && !AbsShift::where('uuid', $value)->where('company_id', $companyId)->exists()) {
                    $fail(__('absence.validation.shift_uuid_not_found'));
                }
            }],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => __('operational.marketings.validation.name_required'),
            'name.max'       => __('operational.marketings.validation.name_max'),
            'phone.required' => __('operational.marketings.validation.phone_required'),
            'phone.unique'   => __('operational.marketings.validation.phone_unique'),
            'phone.max'      => __('operational.marketings.validation.phone_max'),
            'email.email'    => __('operational.marketings.validation.email_invalid'),
            'email.unique'   => __('operational.marketings.validation.email_unique'),
            'password.min'   => __('operational.marketings.validation.password_min'),
        ];
    }
}
