<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $position = $this->route('position');
        $positionId = $position instanceof Position ? $position->id : null;

        return [
            'name' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:100',
                Rule::unique('positions', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($positionId),
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
