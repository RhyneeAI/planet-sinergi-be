<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\AbsJabatan;
use App\Models\AbsShift;
use App\Models\SubCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof \App\Models\User ? $user->id : null;

        return [
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:150'],
            'phone' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:6'],
            'role' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                Rule::enum(Role::class),
                Rule::notIn(Role::commissionMarketingValues()),
            ],
            'jabatan_uuid' => ['nullable', 'uuid', function ($attribute, $value, $fail) {
                if ($value && !AbsJabatan::where('uuid', $value)->where('company_id', $this->user()->company_id)->exists()) {
                    $fail(__('absence.validation.jabatan_uuid_not_found'));
                }
            }],
            'sub_company_uuid' => ['nullable', 'uuid', function ($attribute, $value, $fail) {
                if ($value && !SubCompany::where('uuid', $value)->where('company_id', $this->user()->company_id)->exists()) {
                    $fail(__('absence.validation.sub_company_uuid_not_found'));
                }
            }],
            'shift_uuid' => ['nullable', 'uuid', function ($attribute, $value, $fail) {
                if ($value && !AbsShift::where('uuid', $value)->where('company_id', $this->user()->company_id)->exists()) {
                    $fail(__('absence.validation.shift_uuid_not_found'));
                }
            }],
            'is_active' => ['nullable', 'in:true,false,1,0'],
        ];
    }
}
