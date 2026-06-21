<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class OpsTransferConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmed_amount' => ['required', 'numeric', 'min:0.01'],
            'mandor_proof_files' => ['required', 'array', 'min:1', 'max:3'],
            'mandor_proof_files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'mandor_proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed_amount.required' => __('operational.validation.confirmed_amount_required'),
            'confirmed_amount.numeric' => __('operational.validation.amount_numeric'),
            'confirmed_amount.min' => __('operational.validation.amount_min'),
            'mandor_proof_files.required' => __('operational.validation.mandor_proof_required'),
            'mandor_proof_files.max' => __('operational.validation.mandor_proof_max'),
            'mandor_proof_files.*.file' => __('operational.validation.proof_file_invalid'),
            'mandor_proof_files.*.mimes' => __('operational.validation.proof_file_invalid'),
            'mandor_proof_files.*.max' => __('operational.validation.mandor_proof_file_max'),
            'mandor_proof_file.file' => __('operational.validation.proof_file_invalid'),
            'mandor_proof_file.max' => __('operational.validation.mandor_proof_file_max'),
            'mandor_proof_file.mimes' => __('operational.validation.proof_file_invalid'),
            'note.string' => __('operational.validation.note_invalid'),
            'note.max' => __('operational.validation.note_max'),
        ];
    }
}
