<?php

namespace App\Services\Pos;

use App\Enums\PosInstallmentStatus;
use App\Enums\PosPaymentType;
use App\Enums\PosStockMutationType;
use App\Enums\PosTransactionStatus;
use App\Models\PosProduct;
use App\Models\PosSalesDetail;
use App\Models\PosSalesInstallmentPlan;
use App\Models\PosSalesTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSalesService
{
    public function __construct(
        protected PosStockMutationService $stockMutationService,
    ) {}

    public function store(array $data, User $user): PosSalesTransaction
    {
        $customerId = $data['customer_id'];
        $discount   = $data['discount'] ?? 0;
        $items      = $data['items'];

        $products = $this->resolveProducts($items, $user->company_id);

        $this->validateStock($items, $products);

        $transactionCode = $this->generateTransactionCode($user);

        return DB::transaction(function () use ($data, $items, $products, $transactionCode, $customerId, $discount, $user) {
            $paymentType = PosPaymentType::tryFrom($data['payment_type']);

            $transaction = PosSalesTransaction::create([
                'transaction_code'   => $transactionCode,
                'transaction_date'   => Carbon::parse($data['transaction_date'])->format('Y-m-d H:i:s'),
                'discount'           => $discount,
                'additional_cost'      => $data['additional_cost'] ?? 0,
                'additional_cost_note' => $data['additional_cost_note'] ?? null,
                'total'              => $data['total'],
                'paid'               => $paymentType === PosPaymentType::CICIL ? 0 : ($data['paid'] ?? 0),
                'payment_type'       => $paymentType,
                'transaction_status' => $paymentType === PosPaymentType::CICIL
                                            ? PosTransactionStatus::UNPAID
                                            : PosTransactionStatus::PAID,
                'customer_id'        => $customerId,
                'created_by'         => $user->id,
                'company_id'         => $user->company_id,
            ]);

            foreach ($items as $item) {
                $this->processSalesItem($item, $products, $transaction, $transactionCode, $user);
            }

            if ($paymentType === PosPaymentType::CICIL) {
                $this->createInstallmentPlan($transaction, $customerId, $data['total'], $data['tenor'], $user);
            }

            return $transaction->fresh()->load(['customer', 'createdBy', 'details', 'details.product']);
        });
    }

    public function cancel(PosSalesTransaction $transaction, User $user): PosSalesTransaction
    {
        if ($transaction->transaction_status === PosTransactionStatus::CANCEL) {
            throw ValidationException::withMessages([
                'transaction' => [__('sales_transactions.already_cancelled')],
            ]);
        }

        if ($transaction->transaction_status !== PosTransactionStatus::PAID) {
            throw ValidationException::withMessages([
                'transaction' => [__('sales_transactions.cannot_cancel')],
            ]);
        }

        return DB::transaction(function () use ($transaction, $user) {
            $transaction->update([
                'transaction_status' => PosTransactionStatus::CANCEL,
            ]);

            foreach ($transaction->details as $detail) {
                $product     = $detail->product;
                $stockBefore = (int) $product->stock;
                $stockAfter  = $stockBefore + (int) $detail->quantity;

                $this->stockMutationService->adjustStock($product, $stockAfter);

                $this->stockMutationService->create(
                    product: $product,
                    type: PosStockMutationType::ADJUST_IN,
                    quantity: (int) $detail->quantity,
                    stockBefore: $stockBefore,
                    stockAfter: $stockAfter,
                    notes: "Pembatalan penjualan #{$transaction->transaction_code}",
                    companyId: $transaction->company_id,
                    reference: $detail,
                    createdBy: $user->id,
                );
            }

            return $transaction->fresh()->load(['customer', 'createdBy', 'details', 'details.product']);
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
                'items' => [__('sales_transactions.validation.item_product_not_found')],
            ]);
        }

        return $products;
    }

    protected function validateStock(array $items, Collection $products): void
    {
        foreach ($items as $item) {
            $product = $products->get($item['product_uuid']);
            if ((int) $product->stock < (int) $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => [__('sales_transactions.validation.insufficient_stock', [
                        'product' => $product->name,
                        'stock'   => $product->stock,
                    ])],
                ]);
            }
        }
    }

    protected function generateTransactionCode(User $user): string
    {
        $companyCode = $user->company->code;
        $datePrefix = now()->format('Ymd');
        $prefix = "SO-{$companyCode}{$datePrefix}";

        $lastTransaction = PosSalesTransaction::where('transaction_code', 'like', $prefix . '%')
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

    protected function processSalesItem(
        array $item,
        Collection $products,
        PosSalesTransaction $transaction,
        string $transactionCode,
        User $user,
    ): void {
        $product         = $products->get($item['product_uuid']);
        $itemDiscount    = $item['discount'] ?? 0;
        $sellPrice       = (float) $item['sell_price'];
        $marketingPrice  = (float) ($item['marketing_price'] ?? 0);
        $quantity        = (int) $item['quantity'];
        $subtotal        = $quantity * ($sellPrice - $itemDiscount);
        $stockBefore     = (int) $product->stock;
        $stockAfter      = $stockBefore - $quantity;

        $detail = PosSalesDetail::create([
            'sale_id'         => $transaction->id,
            'product_id'      => $product->id,
            'quantity'        => $quantity,
            'sell_price'      => $sellPrice,
            'marketing_price' => $marketingPrice,
            'discount'        => $itemDiscount,
            'subtotal'        => $subtotal,
            'company_id'      => $user->company_id,
        ]);

        $this->stockMutationService->adjustStock($product, $stockAfter);

        $this->stockMutationService->create(
            product: $product,
            type: PosStockMutationType::SALES_OUT,
            quantity: $quantity,
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            notes: "Penjualan #{$transactionCode}",
            companyId: $user->company_id,
            reference: $detail,
            createdBy: $user->id,
        );
    }

    protected function createInstallmentPlan(
        PosSalesTransaction $transaction,
        int $customerId,
        float $total,
        ?int $tenor,
        User $user,
    ): void {
        PosSalesInstallmentPlan::create([
            'ulid'                 => Str::ulid(),
            'sales_transaction_id' => $transaction->id,
            'customer_id'          => $customerId,
            'total_amount'         => $total,
            'paid_amount'          => 0,
            'tenor'                => $tenor,
            'start_date'           => now()->toDateString(),
            'status'               => PosInstallmentStatus::ACTIVE,
            'company_id'           => $user->company_id,
        ]);
    }
}
