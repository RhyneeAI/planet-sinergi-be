<?php

namespace App\Http\Requests\Absence;

use Illuminate\Foundation\Http\FormRequest;

class AbsEmployeeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['sometimes', 'nullable', 'in:export,data'],
            'jabatan_uuid' => ['sometimes', 'nullable', 'uuid'],
            'sub_company_uuid' => ['sometimes', 'nullable', 'uuid'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
