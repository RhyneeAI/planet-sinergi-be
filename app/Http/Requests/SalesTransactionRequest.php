<?php

namespace App\Http\Requests;

use App\Enums\PaymentType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesTransactionRequest extends FormRequest
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
                Rule::unique('sales_transactions')->ignore($this->salesTransaction?->id),
            ],
            'transaction_date'   => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'date',
            ],
            'discount'           => ['sometimes', 'numeric', 'min:0'],
            'total'              => ['sometimes', 'numeric', 'min:0'],
            'paid'               => ['sometimes', 'numeric', 'min:0'],
            'payment_type'       => [
                'sometimes',
                Rule::enum(PaymentType::class),
            ],
            'transaction_status' => [
                'sometimes',
                Rule::enum(TransactionStatus::class),
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
                    
                    $customerExists = Customer::where('uuid', $value)
                        ->where('company_id', $this->user()->company_id)
                        ->exists();
                    
                    if (!$customerExists) {
                        $fail(__('sales_transactions.validation.customer_not_found'));
                    }
                }
            ],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_uuid' => ['required', 'string', 'uuid', 'exists:products,uuid'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.sell_price'   => ['required', 'numeric', 'min:0'],
            'items.*.discount'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $total    = $this->total ?? 0;
                $discount = $this->discount ?? 0;
                $paid     = $this->paid ?? 0;

                if ($discount > $total) {
                    $validator->errors()->add(
                        'discount',
                        __('sales_transactions.validation.discount_greater_than_total')
                    );
                }

                if ($paid < $total) {
                    $validator->errors()->add(
                        'paid',
                        __('sales_transactions.validation.paid_lower_than_total')
                    );
                }
            }
        ];
    }

    public function getCustomerId(): ?int
    {
        if (!$this->customer_uuid) return null;
        return Customer::where('uuid', $this->customer_uuid)
            ->where('company_id', $this->user()->company_id)
            ->value('id');
    }

    public function messages(): array
    {
        return [
            'transaction_code.required'     => __('sales_transactions.validation.transaction_code_required'),
            'transaction_code.unique'       => __('sales_transactions.validation.transaction_code_unique'),
            'transaction_date.required'     => __('sales_transactions.validation.transaction_date_required'),
            'transaction_date.date'         => __('sales_transactions.validation.transaction_date_invalid'),
            'customer_id.exists'            => __('sales_transactions.validation.customer_id_not_found'),
            'payment_type.required'         => __('sales_transactions.validation.payment_type_required'),
            'payment_type.enum'             => __('sales_transactions.validation.payment_type_invalid'),
            'transaction_status.enum'       => __('sales_transactions.validation.transaction_status_invalid'),
            'customer_uuid.uuid'            => __('sales_transactions.validation.customer_uuid_invalid'),
            'customer_uuid.exists'          => __('sales_transactions.validation.customer_not_found'),
            'total.required'                => __('sales_transactions.validation.total_required'),
            'paid.required'                 => __('sales_transactions.validation.paid_required'),
            'items.required'                => __('sales_transactions.validation.items_required'),
            'items.min'                     => __('sales_transactions.validation.items_min'),
            'items.*.product_uuid.required' => __('sales_transactions.validation.item_product_required'),
            'items.*.product_uuid.exists'   => __('sales_transactions.validation.item_product_not_found'),
            'items.*.quantity.required'     => __('sales_transactions.validation.item_quantity_required'),
            'items.*.quantity.min'          => __('sales_transactions.validation.item_quantity_min'),
            'items.*.sell_price.required'   => __('sales_transactions.validation.item_sell_price_required'),
            'items.*.sell_price.min'        => __('sales_transactions.validation.item_sell_price_min'),
        ];
    }
}
