<?php

namespace App\Services\Pos;

use App\Enums\PosInstallmentStatus;
use App\Enums\PosPaymentType;
use App\Enums\PosStockMutationType;
use App\Enums\PosTransactionStatus;
use App\Models\PosProduct;
use App\Models\PosPurchaseDetail;
use App\Models\PosPurchaseInstallmentPlan;
use App\Models\PosPurchaseTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosPurchaseService
{
    public function __construct(
        protected PosStockMutationService $stockMutationService,
    ) {}

    public function store(array $data, User $user): PosPurchaseTransaction
    {
        $supplierId = $data['supplier_id'];
        $items      = $data['items'];

        $products = $this->resolveProducts($items, $user->company_id);

        $transactionCode = $this->generateTransactionCode($user);

        return DB::transaction(function () use ($data, $items, $products, $transactionCode, $supplierId, $user) {
            $paymentType = PosPaymentType::tryFrom($data['payment_type']);

            $transaction = PosPurchaseTransaction::create([
                'transaction_code'   => $transactionCode,
                'transaction_date'   => $data['transaction_date'] . ' ' . date('H:i:s'),
                'discount'           => $data['discount'] ?? 0,
                'total'              => $data['total'],
                'paid'               => $data['paid'] ?? 0,
                'payment_type'       => $paymentType,
                'transaction_status' => $paymentType === PosPaymentType::CICIL
                                            ? PosTransactionStatus::UNPAID
                                            : PosTransactionStatus::PAID,
                'supplier_id'        => $supplierId,
                'created_by'         => $user->id,
                'company_id'         => $user->company_id,
            ]);

            foreach ($items as $item) {
                $this->processPurchaseItem($item, $products, $transaction, $transactionCode, $user);
            }

            if ($paymentType === PosPaymentType::CICIL) {
                $this->createInstallmentPlan($transaction, $supplierId, $data['total'], $user);
            }

            return $transaction->fresh()->load(['supplier', 'createdBy', 'details', 'details.product']);
        });
    }

    public function cancel(PosPurchaseTransaction $transaction, User $user): PosPurchaseTransaction
    {
        if ($transaction->transaction_status === PosTransactionStatus::CANCEL) {
            throw ValidationException::withMessages([
                'transaction' => [__('pos.purchase_transactions.already_cancelled')],
            ]);
        }

        if ($transaction->transaction_status !== PosTransactionStatus::PAID) {
            throw ValidationException::withMessages([
                'transaction' => [__('pos.purchase_transactions.cannot_cancel')],
            ]);
        }

        return DB::transaction(function () use ($transaction, $user) {
            $transaction->update([
                'transaction_status' => PosTransactionStatus::CANCEL,
            ]);

            foreach ($transaction->details as $detail) {
                $product     = $detail->product;
                $stockBefore = (int) $product->stock;
                $stockAfter  = $stockBefore - (int) $detail->quantity;

                $this->stockMutationService->adjustStock($product, $stockAfter);

                $this->stockMutationService->create(
                    product: $product,
                    type: PosStockMutationType::ADJUST_OUT,
                    quantity: (int) $detail->quantity,
                    stockBefore: $stockBefore,
                    stockAfter: max(0, $stockAfter),
                    notes: "Pembatalan pembelian #{$transaction->transaction_code}",
                    companyId: $transaction->company_id,
                    reference: $detail,
                    createdBy: $user->id,
                );
            }

            return $transaction->fresh()->load(['supplier', 'createdBy', 'details', 'details.product']);
        });
    }

    protected function resolveProducts(array $items, int $companyId): Collection
    {
        $productUuids = collect($items)->pluck('product_uuid');
        $products = PosProduct::whereIn('uuid', $productUuids)
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('uuid');

        if ($products->count() !== $productUuids->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => [__('pos.purchase_transactions.validation.item_product_not_found')],
            ]);
        }

        return $products;
    }

    protected function generateTransactionCode(User $user): string
    {
        $companyCode = $user->company->code;
        $datePrefix = now()->format('Ymd');
        $prefix = "PO-{$companyCode}{$datePrefix}";

        $lastTransaction = PosPurchaseTransaction::where('transaction_code', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastTransaction) {
            $lastNumber = (int) substr($lastTransaction->transaction_code, -4);
            $sequence = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sequence = '001';
        }

        return $prefix . $sequence;
    }

    protected function processPurchaseItem(
        array $item,
        Collection $products,
        PosPurchaseTransaction $transaction,
        string $transactionCode,
        User $user,
    ): void {
        $product    = $products->get($item['product_uuid']);
        $buyPrice   = (float) $item['buy_price'];
        $quantity   = (int) $item['quantity'];
        $subtotal   = $quantity * $buyPrice;
        $stockBefore = (int) $product->stock;
        $stockAfter  = $stockBefore + $quantity;

        $detail = PosPurchaseDetail::create([
            'purchase_id' => $transaction->id,
            'product_id'  => $product->id,
            'quantity'    => $quantity,
            'buy_price'   => $buyPrice,
            'subtotal'    => $subtotal,
            'company_id'  => $user->company_id,
        ]);

        $product->update([
            'stock'                => $stockAfter,
            'base_price'           => ($product->base_price + $buyPrice) / 2,
            'last_purchase_price'  => $buyPrice,
        ]);

        $this->stockMutationService->create(
            product: $product,
            type: PosStockMutationType::PURCHASE_IN,
            quantity: $quantity,
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            notes: "Pembelian #{$transactionCode}",
            companyId: $user->company_id,
            reference: $detail,
            createdBy: $user->id,
        );
    }

    protected function createInstallmentPlan(
        PosPurchaseTransaction $transaction,
        int $supplierId,
        float $total,
        User $user,
    ): void {
        PosPurchaseInstallmentPlan::create([
            'ulid'                      => Str::ulid(),
            'purchase_transaction_id'   => $transaction->id,
            'supplier_id'               => $supplierId,
            'total_amount'              => $total,
            'paid_amount'               => 0,
            'start_date'                => now()->toDateString(),
            'status'                    => PosInstallmentStatus::ACTIVE,
            'company_id'                => $user->company_id,
        ]);
    }
}
