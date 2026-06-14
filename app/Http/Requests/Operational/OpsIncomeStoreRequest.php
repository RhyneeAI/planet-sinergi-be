<?php

namespace App\Http\Requests\Operational;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class OpsIncomeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mandor_uuid' => [
                'required',
                'string',
                'uuid',
                function ($attribute, $value, $fail) {
                    $exists = User::where('uuid', $value)
                        ->where('company_id', $this->user()->company_id)
                        ->where('role', Role::MANDOR)
                        ->where('is_active', true)
                        ->exists();

                    if (!$exists) {
                        $fail(__('operational.validation.mandor_uuid_not_found'));
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'mandor_uuid.required' => __('operational.validation.mandor_uuid_required'),
            'mandor_uuid.string' => __('operational.validation.mandor_uuid_string'),
            'mandor_uuid.uuid' => __('operational.validation.mandor_uuid_invalid'),
            'mandor_uuid.exists' => __('operational.validation.mandor_uuid_not_found'),
            'name.required' => __('operational.validation.name_required'),
            'name.string' => __('operational.validation.name_string'),
            'name.max' => __('operational.validation.name_max'),
            'amount.required' => __('operational.validation.amount_required'),
            'amount.numeric' => __('operational.validation.amount_numeric'),
            'amount.min' => __('operational.validation.amount_min'),
            'date.required' => __('operational.validation.date_required'),
            'date.date' => __('operational.validation.date_invalid'),
            'reason.required' => __('operational.validation.reason_required'),
            'reason.string' => __('operational.validation.reason_string'),
            'proof_file.required' => __('operational.validation.proof_file_required'),
            'proof_file.file' => __('operational.validation.proof_file_file'),
            'proof_file.mimes' => __('operational.validation.proof_file_invalid'),
            'proof_file.max' => __('operational.validation.proof_file_max'),
            'note.string' => __('operational.validation.note_invalid'),
            'note.max' => __('operational.validation.note_max'),
        ];
    }

    public function getMandorId(): ?int
    {
        return User::where('uuid', $this->mandor_uuid)
            ->where('company_id', $this->user()->company_id)
            ->where('role', Role::MANDOR)
            ->value('id');
    }
}
