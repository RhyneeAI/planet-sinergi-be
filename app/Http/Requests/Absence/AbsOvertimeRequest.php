<?php

namespace App\Http\Requests\Absence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbsOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['required', 'string', 'max:1000'],
        ];

        if ($this->isMethod('post')) {
            $rules['user_ids'] = ['required', 'array', 'min:1'];
            $rules['user_ids.*'] = ['required', 'exists:users,id'];
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['user_id'] = ['sometimes', 'exists:users,id'];
        }

        return $rules;
    }
}
