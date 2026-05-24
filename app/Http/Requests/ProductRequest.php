<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
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
                Rule::unique('products')->where(function ($query) {
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
            
            'category_uuid' => ['nullable', 'uuid', 'exists:categories,uuid'],
            'unit_uuid' => ['nullable', 'uuid', 'exists:units,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => __('products.validation.name_required'),
            'name.string'               => __('products.validation.name_string'),
            'name.max'                  => __('products.validation.name_max', ['max'                => 255]),
            'code.unique'               => __('products.validation.code_unique'),
            'base_price.numeric'        => __('products.validation.base_price_numeric'),
            'sales_price.required'      => __('products.validation.sales_price_required'),
            'sales_price.numeric'       => __('products.validation.sales_price_numeric'),
            'marketing_price.numeric'   => __('products.validation.marketing_price_numeric'),
            'marketing_price.min'       => __('products.validation.marketing_price_min'),
            'stock.integer'             => __('products.validation.stock_integer'),
            'min_stock.integer'         => __('products.validation.min_stock_integer'),
            
            'category_uuid.exists'      => __('products.validation.category_uuid_exists'),
            'unit_uuid.exists'          => __('products.validation.unit_uuid_exists'),
        ];
    }
}