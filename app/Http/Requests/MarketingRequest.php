<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
            ],
            'username' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($this->user_model?->id),
            ],
            'email' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->user_model?->id),
            ],
            'password' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'min:8',
            ],
            'address'  => ['sometimes', 'nullable', 'string'],
            'phone'    => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => __('marketings.validation.name_required'),
            'username.required' => __('marketings.validation.username_required'),
            'username.unique'   => __('marketings.validation.username_unique'),
            'email.required'    => __('marketings.validation.email_required'),
            'email.unique'      => __('marketings.validation.email_unique'),
            'password.required' => __('marketings.validation.password_required'),
            'password.min'      => __('marketings.validation.password_min'),
            'phone.max'         => __('marketings.validation.phone_max'),
        ];
    }
}