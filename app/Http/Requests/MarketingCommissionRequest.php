<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class MarketingCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from'      => ['required', 'date'],
            'date_to'        => ['required', 'date', 'after_or_equal:date_from'],
            'marketing_uuid' => ['nullable', 'string', 'uuid', 'exists:users,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required'     => __('reports.validation.marketingCommission.date_from_required'),
            'date_to.required'       => __('reports.validation.marketingCommission.date_to_required'),
            'date_to.after_or_equal' => __('reports.validation.marketingCommission.date_to_after'),
            'marketing_uuid.uuid'    => __('reports.validation.marketingCommission.marketing_uuid_invalid'),
            'marketing_uuid.exists'  => __('reports.validation.marketingCommission.marketing_not_found'),
        ];
    }
}