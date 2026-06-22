<?php

namespace App\Http\Requests\Pos;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\PosCategory;

class PosCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        $categoryId = $this->category?->id;
        
        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($categoryId) {
                    // Case-insensitive duplicate check within same company
                    $exists = PosCategory::where('company_id', $this->user()->company_id)
                        ->whereRaw('LOWER(name) = LOWER(?)', [$value])
                        ->when($categoryId, function ($query) use ($categoryId) {
                            return $query->where('id', '!=', $categoryId);
                        })
                        ->exists();
                    
                    if ($exists) {
                        $fail(__('pos.categories.validation.name_unique'));
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('pos.categories.validation.name_required'),
            'name.string'   => __('pos.categories.validation.name_string'),
            'name.max'      => __('pos.categories.validation.name_max', ['max' => 255]),
        ];
    }
}
