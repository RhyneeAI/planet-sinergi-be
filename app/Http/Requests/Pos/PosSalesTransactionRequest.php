<?php

namespace App\Http\Requests\Pos;

use App\Enums\PosPaymentType;
use App\Enums\PosTransactionStatus;
use App\Models\PosCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosSalesTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_code'   => [
                'sometimes', // Auto-generated on POST, so never required from client
                'string',
                'max:255',
                Rule::unique('pos_sales_transactions')->ignore($this->salesTransaction?->id),
            ],
            'transaction_date'   => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'date',
            ],
            'discount'           => ['sometimes', 'numeric', 'min:0'],
            'additional_cost'      => ['sometimes', 'numeric', 'min:0'],
            'additional_cost_note' => ['nullable', 'string', 'max:255'],
            'total'              => ['sometimes', 'numeric', 'min:0'],
            'paid'               => ['sometimes', 'numeric', 'min:0'],
            'payment_type'       => [
                'sometimes',
                Rule::enum(PosPaymentType::class),
            ],
            'transaction_status' => [
                'sometimes',
                Rule::enum(PosTransactionStatus::class),
            ],
            'customer_uuid'        => [
                'nullable',
                'string',
                'uuid',
                function ($attribute, $value, $fail) {
                    if (!$value) return; // nullable
                    
                    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                        return; // uuid rule handle error
                    }
                    
                    $customerExists = PosCustomer::where('uuid', $value)
                        ->where('company_id', $this->user()->company_id)
                        ->exists();
                    
                    if (!$customerExists) {
                        $fail(__('pos.sales_transactions.validation.customer_not_found'));
                    }
                }
            ],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.product_uuid'      => ['required', 'string', 'uuid', 'exists:pos_products,uuid'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
            'items.*.sell_price'        => ['required', 'numeric', 'min:0'],
            'items.*.marketing_price'   => ['required', 'numeric', 'min:0'],
            'items.*.discount'          => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                // Jika cicilan, skip validasi paid
                if ($this->payment_type === PosPaymentType::CICIL->value) {
                    return;
                }

                $total    = $this->total ?? 0;
                $discount = $this->discount ?? 0;
                $paid     = $this->paid ?? 0;

                if ($discount > $total) {
                    $validator->errors()->add(
                        'discount',
                        __('pos.sales_transactions.validation.discount_greater_than_total')
                    );
                }

                if ($paid < $total) {
                    $validator->errors()->add(
                        'paid',
                        __('pos.sales_transactions.validation.paid_lower_than_total')
                    );
                }
            }
        ];
    }

    public function getCustomerId(): ?int
    {
        if (!$this->customer_uuid) return null;
        return PosCustomer::where('uuid', $this->customer_uuid)
            ->where('company_id', $this->user()->company_id)
            ->value('id');
    }

    public function messages(): array
    {
        return [
            'transaction_code.required'             => __('pos.sales_transactions.validation.transaction_code_required'),
            'transaction_code.unique'               => __('pos.sales_transactions.validation.transaction_code_unique'),
            'transaction_date.required'             => __('pos.sales_transactions.validation.transaction_date_required'),
            'transaction_date.date'                 => __('pos.sales_transactions.validation.transaction_date_invalid'),
            'customer_id.exists'                    => __('pos.sales_transactions.validation.customer_id_not_found'),
            'payment_type.required'                 => __('pos.sales_transactions.validation.payment_type_required'),
            'payment_type.enum'                     => __('pos.sales_transactions.validation.payment_type_invalid'),
            'transaction_status.enum'               => __('pos.sales_transactions.validation.transaction_status_invalid'),
            'customer_uuid.uuid'                    => __('pos.sales_transactions.validation.customer_uuid_invalid'),
            'customer_uuid.exists'                  => __('pos.sales_transactions.validation.customer_not_found'),
            'additional_cost.numeric'               => __('pos.sales_transactions.validation.additional_cost_numeric'),
            'additional_cost.min'                   => __('pos.sales_transactions.validation.additional_cost_min'),
            'total.required'                        => __('pos.sales_transactions.validation.total_required'),
            'paid.required'                         => __('pos.sales_transactions.validation.paid_required'),
            'items.required'                        => __('pos.sales_transactions.validation.items_required'),
            'items.min'                             => __('pos.sales_transactions.validation.items_min'),
            'items.*.product_uuid.required'         => __('pos.sales_transactions.validation.item_product_required'),
            'items.*.product_uuid.exists'           => __('pos.sales_transactions.validation.item_product_not_found'),
            'items.*.quantity.required'             => __('pos.sales_transactions.validation.item_quantity_required'),
            'items.*.quantity.min'                  => __('pos.sales_transactions.validation.item_quantity_min'),
            'items.*.sell_price.required'           => __('pos.sales_transactions.validation.item_sell_price_required'),
            'items.*.sell_price.min'                => __('pos.sales_transactions.validation.item_sell_price_min'),
            'items.*.marketing_price.required'      => __('pos.sales_transactions.validation.item_marketing_price_required'),
        ];
    }
}
