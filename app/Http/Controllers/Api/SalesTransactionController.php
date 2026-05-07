<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\StockMutationType;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesTransactionRequest;
use App\Http\Resources\SalesTransactionResource;
use App\Models\MarketingProduct;
use App\Models\Product;
use App\Models\SalesDetail;
use App\Models\SalesTransaction;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesTransactionController extends Controller
{
    protected array $sortableColumns = ['transaction_code', 'transaction_date', 'transaction_status'];

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'transaction_date'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'transaction_date')
                            : 'transaction_date';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'DESC' ? 'DESC' : 'ASC';

        $transactions = SalesTransaction::with(['customer', 'createdBy'])
            ->when($request->search, fn($q, $search) =>
                $q->where('transaction_code', 'like', "%{$search}%")
            )
            ->when($request->date_from, fn($q, $date) =>
                $q->whereDate('transaction_date', '>=', $date)
            )
            ->when($request->date_to, fn($q, $date) =>
                $q->whereDate('transaction_date', '<=', $date)
            )
            // ->when($request->status, fn($q, $status) =>
            //     $q->where('transaction_status', $status)
            // )
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('sales_transactions.list'),
            'data'    => SalesTransactionResource::collection($transactions),
        ]);
    }

    public function store(SalesTransactionRequest $request)
    {
        DB::beginTransaction();

        try {
            $customerId  = $request->getCustomerId();
            $discount    = $request->discount ?? 0;
            $items       = $request->items;

            // Resolve semua product sekaligus — hindari N+1
            $productUuids = collect($items)->pluck('product_uuid');
            $products     = Product::whereIn('uuid', $productUuids)
                ->where('company_id', $request->user()->company_id)
                ->get()
                ->keyBy('uuid');

            if ($products->count() !== $productUuids->unique()->count()) {
                return response()->json([
                    'success' => false,
                    'message' => __('sales_transactions.validation.item_product_not_found'),
                    'code'    => 422,
                ], 422);
            }

            // Validasi stok cukup untuk semua item sekaligus
            foreach ($items as $item) {
                $product = $products->get($item['product_uuid']);
                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => __('sales_transactions.validation.insufficient_stock', [
                            'product' => $product->name,
                            'stock'   => $product->stock,
                        ]),
                        'code' => 422,
                    ], 422);
                }
            }

            $transactionCode = 'SO-' . strtoupper(Str::random(8)) . '-' . now()->format('Ymd');

            $transaction = SalesTransaction::create([
                'transaction_code'   => $transactionCode,
                'transaction_date'   => $request->transaction_date,
                'discount'           => $discount,
                'total'              => $request->total,
                'paid'               => $request->paid,
                'payment_type'       => $request->payment_type,
                'transaction_status' => TransactionStatus::PAID,
                'customer_id'        => $customerId,
                'created_by'         => $request->user()->id,
                'company_id'         => $request->user()->company_id,
            ]);

            foreach ($items as $item) {
                /** @var Product $product */
                $product     = $products->get($item['product_uuid']);
                $itemDiscount = $item['discount'] ?? 0;

                // Harga dari request sell_price
                $sellPrice = $item['sell_price'];

                $subtotal    = ($item['quantity'] * $sellPrice) - $itemDiscount;
                $stockBefore = $product->stock;
                $stockAfter  = $stockBefore - $item['quantity'];

                $detail = SalesDetail::create([
                    'sale_id'    => $transaction->id,
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'sell_price' => $sellPrice,
                    'discount'   => $itemDiscount,
                    'subtotal'   => $subtotal,
                    'company_id' => $request->user()->company_id,
                ]);

                $product->update(['stock' => $stockAfter]);

                StockMutation::create([
                    'type'         => StockMutationType::SALES_OUT,
                    'quantity'     => $item['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after'  => $stockAfter,
                    'notes'        => "Penjualan #{$transactionCode}",
                    'product_id'   => $product->id,
                    'company_id'   => $request->user()->company_id,
                    'reference_id' => $detail->id,
                    'created_by'   => $request->user()->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('sales_transactions.stored'),
                'data'    => new SalesTransactionResource(
                    $transaction->load(['customer', 'createdBy', 'details.product'])
                ),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(SalesTransaction $salesTransaction)
    {
        return response()->json([
            'success' => true,
            'message' => __('sales_transactions.detail'),
            'data'    => new SalesTransactionResource(
                $salesTransaction->load(['customer', 'createdBy', 'details.product'])
            ),
        ]);
    }

    public function cancel(SalesTransaction $salesTransaction)
    {
        if ($salesTransaction->transaction_status === TransactionStatus::CANCEL) {
            return response()->json([
                'success' => false,
                'message' => __('sales_transactions.already_cancelled'),
                'code'    => 422,
            ], 422);
        }

        if ($salesTransaction->transaction_status !== TransactionStatus::PAID) {
            return response()->json([
                'success' => false,
                'message' => __('sales_transactions.cannot_cancel'),
                'code'    => 422,
            ], 422);
        }

        DB::beginTransaction();

        try {
            $salesTransaction->update([
                'transaction_status' => TransactionStatus::CANCEL,
            ]);

            // Rollback stok per detail — ADJUST_IN karena stok dikembalikan
            foreach ($salesTransaction->details as $detail) {
                $product     = $detail->product;
                $stockBefore = $product->stock;
                $stockAfter  = $stockBefore + $detail->quantity;

                $product->update(['stock' => $stockAfter]);

                StockMutation::create([
                    'type'         => StockMutationType::ADJUST_IN,
                    'quantity'     => $detail->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after'  => $stockAfter,
                    'notes'        => "Pembatalan penjualan #{$salesTransaction->transaction_code}",
                    'product_id'   => $product->id,
                    'company_id'   => $salesTransaction->company_id,
                    'reference_id' => $detail->id,
                    'created_by'   => request()->user()->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('sales_transactions.cancelled'),
                'data'    => new SalesTransactionResource(
                    $salesTransaction->load(['customer', 'createdBy', 'details.product'])
                ),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}