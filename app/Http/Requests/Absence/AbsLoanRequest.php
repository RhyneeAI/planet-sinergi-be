<?php

namespace App\Http\Requests\Absence;

use Illuminate\Foundation\Http\FormRequest;

class AbsLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'uuid', 'exists:users,uuid'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenor_months.max' => __('absence.validation.tenor_max_4'),
            'tenor_months.min' => __('absence.validation.tenor_min_1'),
        ];
    }
}
