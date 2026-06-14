<?php

namespace App\Http\Controllers\Api\Operational;

use App\Http\Controllers\Controller;
use App\Http\Resources\Operational\OpsWalletResource;
use App\Http\Resources\Operational\OpsWalletTransactionResource;
use App\Models\OpsWalletTransaction;
use App\Services\Operational\OpsWalletService;
use Illuminate\Http\Request;

class OpsWalletController extends Controller
{
    protected array $sortableColumns = ['type', 'amount', 'reference_type', 'created_at'];

    public function __construct(
        protected OpsWalletService $walletService,
    ) {}

    public function show(Request $request)
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        return response()->json([
            'success' => true,
            'message' => __('operational.wallet.detail'),
            'data' => new OpsWalletResource($wallet->load('mandor')),
        ]);
    }

    public function transactions(Request $request)
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        $orderByKey = in_array($request->input('order_by_key', 'created_at'), $this->sortableColumns)
            ? $request->input('order_by_key', 'created_at')
            : 'created_at';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'ASC' ? 'DESC' : 'ASC';

        $transactions = OpsWalletTransaction::with('createdBy')
            ->where('wallet_id', $wallet->id)
            ->when($request->date_from, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.wallet.transactions'),
            'data' => OpsWalletTransactionResource::collection($transactions),
        ]);
    }
}
