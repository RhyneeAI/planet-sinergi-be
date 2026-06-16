<?php

namespace App\Http\Requests\Operational;

use App\Models\SubCompany;
use Illuminate\Foundation\Http\FormRequest;

class OpsMandorIncomeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sub_company_uuid' => [
                'sometimes',
                'required',
                'string',
                'uuid',
                function ($attribute, $value, $fail) {
                    $exists = SubCompany::where('uuid', $value)
                        ->where('company_id', $this->user()->company_id)
                        ->where('mandor_id', $this->user()->id)
                        ->where('is_active', true)
                        ->exists();

                    if (!$exists) {
                        $fail(__('operational.validation.sub_company_uuid_not_found'));
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'sub_company_uuid.required' => __('operational.validation.sub_company_uuid_required'),
            'name.required' => __('operational.validation.name_required'),
            'name.string' => __('operational.validation.name_string'),
            'name.max' => __('operational.validation.name_max'),
            'amount.required' => __('operational.validation.amount_required'),
            'amount.numeric' => __('operational.validation.amount_numeric'),
            'amount.min' => __('operational.validation.amount_min'),
            'date.required' => __('operational.validation.date_required'),
            'date.date' => __('operational.validation.date_invalid'),
            'proof_file.file' => __('operational.validation.proof_file_file'),
            'proof_file.mimes' => __('operational.validation.proof_file_invalid'),
            'proof_file.max' => __('operational.validation.proof_file_max'),
            'note.string' => __('operational.validation.note_invalid'),
        ];
    }
}
