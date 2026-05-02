<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\MarketingProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketingProductRequest extends FormRequest
{
    // Cache hasil query agar tidak query ulang
    protected ?int $resolvedProductId  = null;
    protected ?int $resolvedMarketingId = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_uuid' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'exists:products,uuid',
            ],
            'marketing_uuid' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                Rule::exists('users', 'uuid')
                    ->where('role', Role::MARKETING)
                    ->where('company_id', $this->user()->company_id),
            ],
            'marketing_price' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'numeric',
                'min:0',
            ],
        ];
    }

    // Duplikat check dipindah ke after() — dijalankan setelah rules lulus
    public function after(): array
    {
        return [
            function ($validator) {
                // Hanya cek duplikat jika product_uuid & marketing_uuid valid
                if ($validator->errors()->hasAny(['product_uuid', 'marketing_uuid'])) {
                    return;
                }

                $productId   = $this->getProductId();
                $marketingId = $this->getMarketingId();

                if (!$productId || !$marketingId) return;

                $exists = MarketingProduct::where('product_id', $productId)
                    ->where('marketing_id', $marketingId)
                    ->where('company_id', $this->user()->company_id)
                    ->when($this->marketing_product, fn($q) =>
                        $q->where('id', '!=', $this->marketing_product->id)
                    )
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'marketing_uuid',
                        __('marketing_product.validation.product_already_assigned')
                    );
                }
            }
        ];
    }

    public function getProductId(): ?int
    {
        if (!$this->product_uuid) return null;

        // Gunakan cache agar tidak query dua kali
        return $this->resolvedProductId ??= Product::where('uuid', $this->product_uuid)
            ->value('id');
    }

    public function getMarketingId(): ?int
    {
        if (!$this->marketing_uuid) return null;

        return $this->resolvedMarketingId ??= User::where('uuid', $this->marketing_uuid)
            ->value('id');
    }

    public function messages(): array
    {
        return [
            'product_uuid.required'    => __('marketing_product.validation.product_required'),
            'product_uuid.exists'      => __('marketing_product.validation.product_not_found'),
            'marketing_uuid.required'  => __('marketing_product.validation.marketing_required'),
            'marketing_uuid.exists'    => __('marketing_product.validation.marketing_not_found'),
            'marketing_price.required' => __('marketing_product.validation.price_required'),
            'marketing_price.numeric'  => __('marketing_product.validation.price_numeric'),
            'marketing_price.min'      => __('marketing_product.validation.price_min'),
        ];
    }
}