<?php

namespace App\Http\Requests\Pos;

use App\Enums\PosStockMutationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosStockMutationRequest extends FormRequest
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
                Rule::in([PosStockMutationType::ADJUST_IN->value, PosStockMutationType::ADJUST_OUT->value, PosStockMutationType::OPNAME->value]),
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'product_uuid' => [ 
                'required',
                'uuid', // Validate UUID format first for PostgreSQL compatibility
                'exists:pos_products,uuid', 
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
            'type.required' => __('pos.stock_mutations.validation.type_required'),
            'type.in' => __('pos.stock_mutations.validation.type_in'),
            'quantity.required' => __('pos.stock_mutations.validation.quantity_required'),
            'quantity.integer' => __('pos.stock_mutations.validation.quantity_integer'),
            'quantity.min' => __('pos.stock_mutations.validation.quantity_min'),
            'product_uuid.required' => __('pos.stock_mutations.validation.product_uuid_required'),
            'product_uuid.exists' => __('pos.stock_mutations.validation.product_uuid_exists'),
        ];
    }
}
