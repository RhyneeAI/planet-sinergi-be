<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units')->ignore($this->unit?->id) 
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('units.validation.name_required'),
            'name.string'   => __('units.validation.name_string'),
            'name.max'      => __('units.validation.name_max', ['max' => 255]),
            'name.unique'   => __('units.validation.name_unique'),
        ];
    }
}