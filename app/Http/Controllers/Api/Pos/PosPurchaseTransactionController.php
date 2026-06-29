<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosPurchaseTransactionRequest;
use App\Http\Resources\Pos\PosPurchaseTransactionResource;
use App\Models\PosPurchaseTransaction;
use App\Services\Pos\PosPurchaseService;
use App\Http\Traits\DataTablesResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosPurchaseTransactionController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['transaction_code', 'transaction_date', 'transaction_status', 'total'];

    public function __construct(
        protected PosPurchaseService $purchaseService,
    ) {}

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'transaction_date'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'transaction_date')
                            : 'transaction_date';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'DESC' ? 'DESC' : 'ASC';

        $transactions = PosPurchaseTransaction::with(['supplier', 'createdBy', 'details', 'details.product'])
            ->when($request->date_from, fn($q, $date) =>
                $q->whereDate('transaction_date', '>=', $date)
            )
            ->when($request->date_to, fn($q, $date) =>
                $q->whereDate('transaction_date', '<=', $date)
            )
            ->when($request->status, fn($q, $status) =>
                $q->where('transaction_status', $status)
            )
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(transaction_code) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                        $supplierQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
                    });
                });
            })
            ->when($request->created_by_uuid, fn($q, $uuid) =>
                $q->whereHas('createdBy', fn($u) =>
                    $u->where('uuid', $uuid)
                )
            )
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $transactions, [
                'success' => true,
                'message' => __('pos.purchase_transactions.list'),
                'data'    => PosPurchaseTransactionResource::collection($transactions),
            ])
        );
    }

    public function store(PosPurchaseTransactionRequest $request)
    {
        try {
            $transaction = $this->purchaseService->store(
                data: array_merge($request->validated(), [
                    'supplier_id' => $request->getSupplierId(),
                ]),
                user: $request->user(),
            );

            return response()->json([
                'success' => true,
                'message' => __('pos.purchase_transactions.stored'),
                'data'    => new PosPurchaseTransactionResource($transaction),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
                'code'    => 422,
            ], 422);
        }
    }

    public function show(PosPurchaseTransaction $purchaseTransaction)
    {
        return response()->json([
            'success' => true,
            'message' => __('pos.purchase_transactions.detail'),
            'data'    => new PosPurchaseTransactionResource(
                $purchaseTransaction->load(['supplier', 'createdBy', 'details', 'details.product'])
            ),
        ]);
    }

    public function cancel(Request $request, PosPurchaseTransaction $purchaseTransaction)
    {
        try {
            $transaction = $this->purchaseService->cancel($purchaseTransaction, $request->user());

            return response()->json([
                'success' => true,
                'message' => __('pos.purchase_transactions.cancelled'),
                'data'    => new PosPurchaseTransactionResource($transaction),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
                'code'    => 422,
            ], 422);
        }
    }
}
