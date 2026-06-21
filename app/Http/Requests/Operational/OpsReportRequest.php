<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class OpsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date'   => ['required', 'date', 'before_or_equal:end_date'],
            'end_date'     => ['required', 'date'],
            'mandor_uuid'  => ['nullable', 'string', 'uuid', 'exists:users,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required'        => __('operational.validation.start_date_required'),
            'start_date.date'            => __('operational.validation.start_date_invalid'),
            'start_date.before_or_equal' => __('operational.validation.start_date_before_end'),
            'end_date.required'          => __('operational.validation.end_date_required'),
            'end_date.date'              => __('operational.validation.end_date_invalid'),
            'mandor_uuid.uuid'           => __('operational.validation.mandor_uuid_invalid'),
            'mandor_uuid.exists'         => __('operational.validation.mandor_uuid_not_found'),
        ];
    }
}
