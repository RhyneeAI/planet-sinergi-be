<?php

namespace App\Http\Requests\Pos;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PosProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('pos_products')->where(function ($query) {
                    return $query->where('company_id', $this->user()->company_id);
                })->ignore($this->product?->id),
            ],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'sales_price' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'numeric',
                'min:0',
            ],
            'marketing_price'     => ['nullable', 'numeric', 'min:0'], 
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            
            'category_uuid' => ['nullable', 'uuid', 'exists:pos_categories,uuid'],
            'unit_uuid' => ['nullable', 'uuid', 'exists:pos_units,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => __('pos.products.validation.name_required'),
            'name.string'               => __('pos.products.validation.name_string'),
            'name.max'                  => __('pos.products.validation.name_max', ['max'                => 255]),
            'code.unique'               => __('pos.products.validation.code_unique'),
            'base_price.numeric'        => __('pos.products.validation.base_price_numeric'),
            'sales_price.required'      => __('pos.products.validation.sales_price_required'),
            'sales_price.numeric'       => __('pos.products.validation.sales_price_numeric'),
            'marketing_price.numeric'   => __('pos.products.validation.marketing_price_numeric'),
            'marketing_price.min'       => __('pos.products.validation.marketing_price_min'),
            'stock.integer'             => __('pos.products.validation.stock_integer'),
            'min_stock.integer'         => __('pos.products.validation.min_stock_integer'),
            
            'category_uuid.exists'      => __('pos.products.validation.category_uuid_exists'),
            'unit_uuid.exists'          => __('pos.products.validation.unit_uuid_exists'),
        ];
    }
}
