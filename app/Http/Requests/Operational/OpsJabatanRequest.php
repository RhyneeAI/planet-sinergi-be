<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpsJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jabatan = $this->route('absJabatan');
        $jabatanId = $jabatan instanceof \App\Models\AbsJabatan ? $jabatan->id : null;

        return [
            'name' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:100',
                Rule::unique('abs_jabatans', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($jabatanId),
            ],
            'daily_rate' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'numeric',
                'min:0',
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
