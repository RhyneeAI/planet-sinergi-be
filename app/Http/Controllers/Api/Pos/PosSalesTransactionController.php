<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\PosTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosSalesTransactionRequest;
use App\Http\Resources\Pos\PosSalesTransactionResource;
use App\Models\PosSalesTransaction;
use App\Services\Pos\PosSalesService;
use App\Http\Traits\DataTablesResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosSalesTransactionController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['transaction_code', 'transaction_date', 'transaction_status'];

    public function __construct(
        protected PosSalesService $salesService,
    ) {}

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'transaction_date'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'transaction_date')
                            : 'transaction_date';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'DESC' ? 'DESC' : 'ASC';

        $transactions = PosSalesTransaction::with(['customer', 'createdBy'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($request->date_from, fn($q, $date) =>
                $q->whereDate('transaction_date', '>=', $date)
            )
            ->when($request->date_to, fn($q, $date) =>
                $q->whereDate('transaction_date', '<=', $date)
            )
            ->when($request->status, fn($q, $status) =>
                $q->where('transaction_status', $status)
            )
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
                'message' => __('pos.sales_transactions.list'),
                'data'    => PosSalesTransactionResource::collection($transactions),
            ])
        );
    }

    public function store(PosSalesTransactionRequest $request)
    {
        try {
            $transaction = $this->salesService->store(
                data: array_merge($request->validated(), [
                    'customer_id' => $request->getCustomerId(),
                    'marketing_id' => $request->getMarketingId(),
                    'marketing_role' => $request->getMarketingRole(),
                ]),
                user: $request->user(),
            );

            return response()->json([
                'success' => true,
                'message' => __('pos.sales_transactions.stored'),
                'data'    => new PosSalesTransactionResource($transaction),
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

    public function show(PosSalesTransaction $salesTransaction)
    {
        return response()->json([
            'success' => true,
            'message' => __('pos.sales_transactions.detail'),
            'data'    => new PosSalesTransactionResource(
                $salesTransaction->load(['customer', 'createdBy', 'details', 'details.product'])
            ),
        ]);
    }

    public function cancel(Request $request, PosSalesTransaction $salesTransaction)
    {
        try {
            $transaction = $this->salesService->cancel($salesTransaction, $request->user());

            return response()->json([
                'success' => true,
                'message' => __('pos.sales_transactions.cancelled'),
                'data'    => new PosSalesTransactionResource($transaction),
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
