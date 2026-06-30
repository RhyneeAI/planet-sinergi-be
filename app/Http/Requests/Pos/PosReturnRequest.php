<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PosReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_transaction_uuid' => ['required', 'string', 'uuid', 'exists:pos_sales_transactions,ulid'],
            'sales_detail_uuid'      => ['required', 'string', 'uuid', 'exists:pos_sales_details,ulid'],
            'product_uuid'           => ['required', 'string', 'uuid', 'exists:pos_products,uuid'],
            'qty'                    => ['required', 'integer', 'min:1'],
            'reason'                 => ['required', 'string', 'max:1000'],
            'refund_amount'          => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'sales_transaction_uuid.required' => __('pos.sales_transaction_returns.validation.sales_transaction_required'),
            'sales_detail_uuid.required'      => __('pos.sales_transaction_returns.validation.sales_detail_required'),
            'product_uuid.required'            => __('pos.sales_transaction_returns.validation.product_required'),
            'qty.required'                     => __('pos.sales_transaction_returns.validation.qty_required'),
            'qty.min'                          => __('pos.sales_transaction_returns.validation.qty_min'),
            'reason.required'                  => __('pos.sales_transaction_returns.validation.reason_required'),
            'refund_amount.required'           => __('pos.sales_transaction_returns.validation.refund_amount_required'),
        ];
    }
}
