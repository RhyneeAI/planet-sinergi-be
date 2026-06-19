<?php

namespace App\Http\Requests\Operational;

use App\Enums\OpsExpenseType;
use App\Enums\Role;
use App\Models\SubCompany;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpsExpenseRequest extends FormRequest
{
    use ValidatesOperationalProofFiles;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->user()->role === Role::MANDOR) {
            return $this->mandorRules();
        }

        return $this->adminRules();
    }

    protected function mandorRules(): array
    {
        $isStore = $this->isMethod('POST');

        return [
            'sub_company_uuid' => [
                $isStore ? 'required' : 'sometimes',
                'string',
                'uuid',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

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
            ...$this->proofFileRules($isStore),
            'note' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ];
    }

    protected function adminRules(): array
    {
        $isStore = $this->isMethod('POST');

        return [
            'expense_type' => [
                $isStore ? 'required' : 'prohibited',
                Rule::in(OpsExpenseType::values()),
            ],
            'mandor_uuid' => [
                'nullable',
                'required_if:expense_type,' . OpsExpenseType::MANDOR->value,
                'string',
                'uuid',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

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
            'sub_company_uuid' => [
                'nullable',
                'required_if:expense_type,' . OpsExpenseType::MANDOR->value,
                'string',
                'uuid',
                function ($attribute, $value, $fail) {
                    if (!$this->filled('mandor_uuid') || !$value) {
                        return;
                    }

                    $mandorId = User::where('uuid', $this->mandor_uuid)
                        ->where('company_id', $this->user()->company_id)
                        ->where('role', Role::MANDOR)
                        ->value('id');

                    if (!$mandorId) {
                        return;
                    }

                    $exists = SubCompany::where('uuid', $value)
                        ->where('company_id', $this->user()->company_id)
                        ->where('mandor_id', $mandorId)
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
            ...$this->proofFileRules($isStore),
            'note' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_type.required' => __('operational.validation.expense_type_required'),
            'expense_type.in' => __('operational.validation.expense_type_invalid'),
            'mandor_uuid.required_if' => __('operational.validation.mandor_uuid_required'),
            'sub_company_uuid.required_if' => __('operational.validation.sub_company_uuid_required'),
            'name.required' => __('operational.validation.name_required'),
            'name.string' => __('operational.validation.name_string'),
            'name.max' => __('operational.validation.name_max'),
            'amount.required' => __('operational.validation.amount_required'),
            'amount.numeric' => __('operational.validation.amount_numeric'),
            'amount.min' => __('operational.validation.amount_min'),
            'date.required' => __('operational.validation.date_required'),
            'date.date' => __('operational.validation.date_invalid'),
            ...$this->proofFileMessages(),
            'note.string' => __('operational.validation.note_invalid'),
        ];
    }
}
