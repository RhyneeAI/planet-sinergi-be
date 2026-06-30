<?php

namespace Database\Seeders;

use App\Enums\PosPaymentType;
use App\Enums\PosStockMutationType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\PosCustomer;
use App\Models\PosProduct;
use App\Models\PosReturn;
use App\Models\PosSalesDetail;
use App\Models\PosSalesTransaction;
use App\Models\User;
use App\Services\Pos\PosStockMutationService;
use Database\Seeders\Concerns\SeedsSalesTransactions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PosSalesDemoSeeder extends Seeder
{
    use SeedsSalesTransactions;

    public function run(): void
    {
        $company = Company::find(1);

        if (!$company) {
            return;
        }

        $kasir = User::where('phone', '087777888888')->first();
        $marketingLead = User::where('phone', '081234567881')->first();
        $marketingMember = User::where('phone', '081234567882')->first();
        $marketingTetap = User::where('phone', '081234567883')->first();

        if (!$kasir || !$marketingLead || !$marketingMember || !$marketingTetap) {
            return;
        }

        $products = PosProduct::where('company_id', $company->id)
            ->whereIn('code', ['PRD-001', 'PRD-002', 'PRD-003', 'PRD-004'])
            ->get()
            ->keyBy('code');

        if ($products->count() < 4) {
            return;
        }

        $customer = PosCustomer::where('company_id', $company->id)->where('name', 'Andi')->first();

        $this->seedSalesTransactions($company, $products->all(), [
            [
                'date' => '2026-06-10 10:00:00',
                'customer_id' => $customer?->id,
                'payment' => PosPaymentType::CASH,
                'discount' => 0,
                'created_by' => $kasir->id,
                'marketing_id' => $marketingLead->id,
                'marketing_role' => Role::MARKETING_LEAD,
                'items' => [
                    ['code' => 'PRD-001', 'qty' => 5, 'price' => 4000, 'marketing_price' => 3500],
                    ['code' => 'PRD-002', 'qty' => 2, 'price' => 6000, 'marketing_price' => 5000],
                ],
            ],
            [
                'date' => '2026-06-12 14:30:00',
                'customer_id' => $customer?->id,
                'payment' => PosPaymentType::CASH,
                'discount' => 1000,
                'created_by' => $kasir->id,
                'marketing_id' => $marketingMember->id,
                'marketing_role' => Role::MARKETING,
                'items' => [
                    ['code' => 'PRD-003', 'qty' => 3, 'price' => 8000, 'marketing_price' => 6500],
                    ['code' => 'PRD-004', 'qty' => 1, 'price' => 18000, 'marketing_price' => 16000],
                ],
            ],
            [
                'date' => '2026-06-15 09:15:00',
                'customer_id' => null,
                'payment' => PosPaymentType::CASH,
                'discount' => 0,
                'created_by' => $kasir->id,
                'marketing_id' => $marketingTetap->id,
                'marketing_role' => null,
                'items' => [
                    ['code' => 'PRD-001', 'qty' => 10, 'price' => 4000, 'marketing_price' => 3500],
                ],
            ],
        ], 'SO-DEMO');

        $this->seedSalesReturn(
            transactionCode: 'SO-DEMO-0002',
            productCode: 'PRD-003',
            qty: 1,
            refundAmount: 8000,
            reason: 'Produk kemasan rusak',
            createdBy: $kasir,
        );
    }

    protected function seedSalesReturn(
        string $transactionCode,
        string $productCode,
        int $qty,
        float $refundAmount,
        string $reason,
        User $createdBy,
    ): void {
        $transaction = PosSalesTransaction::where('transaction_code', $transactionCode)->first();

        if (!$transaction) {
            return;
        }

        $product = PosProduct::where('code', $productCode)
            ->where('company_id', $transaction->company_id)
            ->first();

        if (!$product) {
            return;
        }

        $detail = PosSalesDetail::where('sale_id', $transaction->id)
            ->where('product_id', $product->id)
            ->first();

        if (!$detail) {
            return;
        }

        if (PosReturn::where('sales_detail_id', $detail->id)->exists()) {
            return;
        }

        $stockMutationService = app(PosStockMutationService::class);
        $stockBefore = (int) $product->stock;
        $stockAfter = $stockBefore + $qty;

        $return = PosReturn::create([
            'ulid' => (string) Str::ulid(),
            'sales_transaction_id' => $transaction->id,
            'sales_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'reason' => $reason,
            'refund_amount' => $refundAmount,
            'status' => 'processed',
            'created_by' => $createdBy->id,
            'company_id' => $transaction->company_id,
        ]);

        $stockMutationService->adjustStock($product, $stockAfter);

        $stockMutationService->create(
            product: $product,
            type: PosStockMutationType::RETURN_IN,
            quantity: $qty,
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            notes: "Retur #{$transaction->transaction_code} - {$reason}",
            companyId: $transaction->company_id,
            reference: $return,
            createdBy: $createdBy->id,
        );
    }
}
