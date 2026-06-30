<?php

namespace App\Http\Requests\Operational;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OpsMarketingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;
        $companyId = $this->user()->company_id;
        $isStore = $this->isMethod('POST');

        return [
            'name' => [$isStore ? 'required' : 'sometimes', 'string', 'max:255'],
            'phone' => [
                $isStore ? 'required' : 'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')
                    ->where('company_id', $companyId)
                    ->ignore($userId),
            ],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')
                    ->where('company_id', $companyId)
                    ->ignore($userId),
            ],
            'address' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => [
                $isStore ? 'required' : 'prohibited',
                Rule::in(Role::commissionMarketingValues()),
            ],
            'leader_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'uuid')
                    ->where('company_id', $companyId)
                    ->where('role', Role::MARKETING_LEAD->value),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $role = $this->isMethod('POST')
                ? $this->input('role')
                : $this->route('user')?->role?->value;

            if ($role === Role::MARKETING->value && !$this->filled('leader_uuid')) {
                $validator->errors()->add(
                    'leader_uuid',
                    __('operational.marketings.validation.leader_required')
                );
            }

            if ($role === Role::MARKETING_LEAD->value && $this->filled('leader_uuid')) {
                $validator->errors()->add(
                    'leader_uuid',
                    __('operational.marketings.validation.leader_prohibited')
                );
            }

            $targetUser = $this->route('user');
            if ($targetUser instanceof User && $this->has('leader_uuid') && $this->filled('leader_uuid')) {
                if ($targetUser->role === Role::MARKETING_LEAD) {
                    $validator->errors()->add(
                        'leader_uuid',
                        __('operational.marketings.validation.leader_prohibited')
                    );
                }

                if ($targetUser->uuid === $this->leader_uuid) {
                    $validator->errors()->add(
                        'leader_uuid',
                        __('operational.marketings.validation.leader_self')
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => __('operational.marketings.validation.name_required'),
            'name.max' => __('operational.marketings.validation.name_max'),
            'phone.required' => __('operational.marketings.validation.phone_required'),
            'phone.unique' => __('operational.marketings.validation.phone_unique'),
            'phone.max' => __('operational.marketings.validation.phone_max'),
            'email.email' => __('operational.marketings.validation.email_invalid'),
            'email.unique' => __('operational.marketings.validation.email_unique'),
            'role.required' => __('operational.marketings.validation.role_required'),
            'role.in' => __('operational.marketings.validation.role_invalid'),
            'role.prohibited' => __('operational.marketings.validation.role_immutable'),
            'leader_uuid.uuid' => __('operational.marketings.validation.leader_uuid'),
            'leader_uuid.exists' => __('operational.marketings.validation.leader_not_found'),
        ];
    }
}
