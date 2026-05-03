<?php

namespace App\Http\Requests;

use App\Enums\StockMutationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in([StockMutationType::ADJUST_IN->value, StockMutationType::ADJUST_OUT->value, StockMutationType::OPNAME->value]),
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'product_uuid' => [ 
                'required',
                'exists:products,uuid', 
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => __('stock_mutations.validation.type_required'),
            'type.in' => __('stock_mutations.validation.type_in'),
            'quantity.required' => __('stock_mutations.validation.quantity_required'),
            'quantity.integer' => __('stock_mutations.validation.quantity_integer'),
            'quantity.min' => __('stock_mutations.validation.quantity_min'),
            'product_uuid.required' => __('stock_mutations.validation.product_uuid_required'),
            'product_uuid.exists' => __('stock_mutations.validation.product_uuid_exists'),
        ];
    }
}