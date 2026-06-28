<?php

namespace App\Http\Requests\Absence;

use Illuminate\Foundation\Http\FormRequest;

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
            $rules['user_uuids'] = ['required', 'array', 'min:1'];
            $rules['user_uuids.*'] = ['required', 'uuid', 'exists:users,uuid'];
        }

        return $rules;
    }
}
