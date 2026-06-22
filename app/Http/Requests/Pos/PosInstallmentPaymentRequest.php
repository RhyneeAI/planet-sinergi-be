<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PosInstallmentPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'paid_amount' => ['required', 'numeric', 'min:1'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_amount.required' => __('pos.installments.validation.paid_amount_required'),
            'paid_amount.numeric'  => __('pos.installments.validation.paid_amount_numeric'),
            'paid_amount.min'      => __('pos.installments.validation.paid_amount_min'),
        ];
    }
}
