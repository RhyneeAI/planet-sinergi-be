<?php

namespace Database\Seeders\Concerns;

use App\Enums\PosPaymentType;
use App\Enums\PosTransactionStatus;
use App\Models\Company;
use App\Models\PosProduct;
use App\Models\PosSalesDetail;
use App\Models\PosSalesTransaction;
use Illuminate\Support\Str;

trait SeedsSalesTransactions
{
    protected function seedSalesTransactions(
        Company $company,
        array $productsByCode,
        array $salesData,
        string $codePrefix,
    ): void {
        foreach ($salesData as $index => $sale) {
            $trxCode = $codePrefix . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            if (PosSalesTransaction::where('transaction_code', $trxCode)->exists()) {
                continue;
            }

            $total = collect($sale['items'])->sum(fn ($item) => $item['qty'] * $item['price']);
            $discount = $sale['discount'] ?? 0;
            $totalAfterDiscount = $total - $discount;

            $transaction = PosSalesTransaction::create([
                'ulid' => (string) Str::ulid(),
                'transaction_code' => $trxCode,
                'transaction_date' => $sale['date'],
                'discount' => $discount,
                'total' => $totalAfterDiscount,
                'paid' => $totalAfterDiscount,
                'payment_type' => $sale['payment'],
                'transaction_status' => PosTransactionStatus::PAID,
                'customer_id' => $sale['customer_id'] ?? null,
                'created_by' => $sale['created_by'],
                'company_id' => $company->id,
            ]);

            foreach ($sale['items'] as $item) {
                /** @var PosProduct $product */
                $product = $productsByCode[$item['code']];
                $subtotal = $item['qty'] * $item['price'];

                PosSalesDetail::create([
                    'ulid' => (string) Str::ulid(),
                    'sale_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $item['qty'],
                    'sell_price' => $item['price'],
                    'marketing_price' => $item['marketing_price'] ?? $product->marketing_price ?? $product->leader_price,
                    'discount' => 0,
                    'subtotal' => $subtotal,
                    'company_id' => $company->id,
                ]);

                $product->decrement('stock', $item['qty']);
            }
        }
    }
}
