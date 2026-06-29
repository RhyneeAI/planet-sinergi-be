<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\PosStockMutationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosReturnRequest;
use App\Http\Resources\Pos\PosReturnResource;
use App\Models\PosProduct;
use App\Models\PosReturn;
use App\Models\PosSalesDetail;
use App\Models\PosSalesTransaction;
use App\Services\Pos\PosStockMutationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\DataTablesResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosReturnController extends Controller
{
    use DataTablesResponse;

    public function __construct(
        protected PosStockMutationService $stockMutationService,
    ) {}

    public function index(Request $request)
    {
        $returns = PosReturn::with(['product', 'salesTransaction', 'createdBy'])
            ->when($request->search, fn($q, $search) =>
                $q->whereHas('product', fn($p) =>
                    $p->where('name', 'like', "%{$search}%")
                )
            )
            ->orderBy('created_at', 'DESC')
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $returns, [
                'success' => true,
                'message' => __('pos.returns.list'),
                'data'    => PosReturnResource::collection($returns),
            ])
        );
    }

    public function store(PosReturnRequest $request)
    {
        $transaction = PosSalesTransaction::where('ulid', $request->sales_transaction_uuid)->firstOrFail();
        $detail      = PosSalesDetail::where('ulid', $request->sales_detail_uuid)->firstOrFail();
        $product     = PosProduct::where('uuid', $request->product_uuid)->firstOrFail();

        if ($detail->sale_id !== $transaction->id) {
            throw ValidationException::withMessages([
                'sales_detail_uuid' => [__('pos.returns.validation.detail_not_in_transaction')],
            ]);
        }

        if ($detail->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'product_uuid' => [__('pos.returns.validation.product_not_in_detail')],
            ]);
        }

        $returnedQty = PosReturn::where('sales_detail_id', $detail->id)->sum('qty');
        $available   = (int) $detail->quantity - (int) $returnedQty;

        if ($request->qty > $available) {
            throw ValidationException::withMessages([
                'qty' => [__('pos.returns.validation.qty_exceeds_available', ['available' => $available])],
            ]);
        }

        return DB::transaction(function () use ($request, $transaction, $detail, $product) {
            $stockBefore = (int) $product->stock;
            $stockAfter  = $stockBefore + (int) $request->qty;

            $return = PosReturn::create([
                'ulid'                 => Str::ulid(),
                'sales_transaction_id' => $transaction->id,
                'sales_detail_id'      => $detail->id,
                'product_id'           => $product->id,
                'qty'                  => $request->qty,
                'reason'               => $request->reason,
                'refund_amount'        => $request->refund_amount,
                'status'               => 'processed',
                'created_by'           => $request->user()->id,
                'company_id'           => $request->user()->company_id,
            ]);

            $this->stockMutationService->adjustStock($product, $stockAfter);

            $this->stockMutationService->create(
                product: $product,
                type: PosStockMutationType::RETURN_IN,
                quantity: (int) $request->qty,
                stockBefore: $stockBefore,
                stockAfter: $stockAfter,
                notes: "Retur #{$transaction->transaction_code} - {$request->reason}",
                companyId: $request->user()->company_id,
                reference: $return,
                createdBy: $request->user()->id,
            );

            return response()->json([
                'success' => true,
                'message' => __('pos.returns.stored'),
                'data'    => new PosReturnResource(
                    $return->load(['product', 'salesTransaction', 'createdBy'])
                ),
            ], 201);
        });
    }
}
