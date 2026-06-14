<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class OpsExpenseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'note' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('operational.validation.name_required'),
            'name.string' => __('operational.validation.name_string'),
            'name.max' => __('operational.validation.name_max'),
            'amount.required' => __('operational.validation.amount_required'),
            'amount.numeric' => __('operational.validation.amount_numeric'),
            'amount.min' => __('operational.validation.amount_min'),
            'date.required' => __('operational.validation.date_required'),
            'date.invalid' => __('operational.validation.date_invalid'),
            'proof_file.required' => __('operational.validation.proof_file_required'),
            'proof_file.file' => __('operational.validation.proof_file_file'),
            'proof_file.mimes' => __('operational.validation.proof_file_invalid'),
            'proof_file.max' => __('operational.validation.proof_file_max'),
            'note.string' => __('operational.validation.note_invalid'),
            'note.max' => __('operational.validation.note_max'),
            'reason.string' => __('operational.validation.reason_string'),
            'reason.max' => __('operational.validation.reason_max'),
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        dd($validator->errors()->toArray());
    }
}
