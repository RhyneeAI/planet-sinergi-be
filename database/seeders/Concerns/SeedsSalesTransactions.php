<?php

namespace Database\Seeders\Concerns;

use App\Enums\PosPaymentType;
use App\Enums\PosTransactionStatus;
use App\Enums\Role;
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
                'marketing_id' => $sale['marketing_id'] ?? null,
                'created_by' => $sale['created_by'],
                'company_id' => $company->id,
            ]);

            foreach ($sale['items'] as $item) {
                /** @var PosProduct $product */
                $product = $productsByCode[$item['code']];
                $itemDiscount = $item['discount'] ?? 0;
                $sellPrice = (float) $item['price'];
                $marketingPrice = (float) ($item['marketing_price'] ?? $product->marketing_price ?? $product->leader_price);
                $quantity = (int) $item['qty'];
                $subtotal = $quantity * ($sellPrice - $itemDiscount);

                $profits = $this->calculateItemProfits(
                    $product,
                    $sellPrice,
                    $marketingPrice,
                    $quantity,
                    $sale['marketing_role'] ?? null,
                );

                PosSalesDetail::create([
                    'ulid' => (string) Str::ulid(),
                    'sale_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'sell_price' => $sellPrice,
                    'marketing_price' => $marketingPrice,
                    'company_profit' => $profits['company_profit'],
                    'lead_profit' => $profits['lead_profit'],
                    'marketing_profit' => $profits['marketing_profit'],
                    'discount' => $itemDiscount,
                    'subtotal' => $subtotal,
                    'company_id' => $company->id,
                ]);

                $product->decrement('stock', $quantity);
            }
        }
    }

    protected function calculateItemProfits(
        PosProduct $product,
        float $sellPrice,
        float $marketingPrice,
        int $quantity,
        ?Role $marketingRole,
    ): array {
        $basePrice = (float) $product->base_price;
        $leaderPrice = (float) $product->leader_price;
        $companyProfit = ($leaderPrice - $basePrice) * $quantity;

        if ($marketingRole === Role::MARKETING_LEAD) {
            return [
                'company_profit' => $companyProfit,
                'lead_profit' => ($sellPrice - $leaderPrice) * $quantity,
                'marketing_profit' => 0,
            ];
        }

        if ($marketingRole === Role::MARKETING) {
            return [
                'company_profit' => $companyProfit,
                'lead_profit' => ($marketingPrice - $leaderPrice) * $quantity,
                'marketing_profit' => ($sellPrice - $marketingPrice) * $quantity,
            ];
        }

        return [
            'company_profit' => $companyProfit,
            'lead_profit' => 0,
            'marketing_profit' => 0,
        ];
    }
}
