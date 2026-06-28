<?php

namespace App\Http\Requests\Absence;

use Illuminate\Foundation\Http\FormRequest;

class AbsCustomConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z_]+$/'],
            'value' => ['required', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
