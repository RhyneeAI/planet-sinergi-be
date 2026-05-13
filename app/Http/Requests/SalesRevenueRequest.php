<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesRevenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
            'marketing_uuid' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('users', 'uuid')
                    ->where('role', Role::MARKETING)
                    ->where('company_id', $this->user()->company_id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required'     => __('reports.validation.salesRevenue.date_from_required'),
            'date_to.required'       => __('reports.validation.salesRevenue.date_to_required'),
            'date_to.after_or_equal' => __('reports.validation.salesRevenue.date_to_after'),
            'marketing_uuid.uuid'    => __('reports.validation.salesRevenue.marketing_uuid_invalid'),
            'marketing_uuid.exists'  => __('reports.validation.salesRevenue.marketing_not_found'),
        ];
    }
}