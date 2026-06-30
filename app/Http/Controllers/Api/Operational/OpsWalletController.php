<?php

namespace App\Http\Controllers\Api\Operational;

use App\Http\Controllers\Controller;
use App\Http\Resources\Operational\OpsWalletResource;
use App\Http\Resources\Operational\OpsWalletTransactionResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\OpsWalletTransaction;
use App\Services\SubCompanyService;
use App\Services\Operational\OpsWalletService;
use Illuminate\Http\Request;

class OpsWalletController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['type', 'amount', 'reference_type', 'created_at'];

    public function __construct(
        protected OpsWalletService $walletService,
        protected SubCompanyService $subCompanyService,
    ) {}

    public function show(Request $request)
    {
        $subCompany = $this->resolveSubCompany($request);

        $wallet = $this->walletService->getOrCreateWallet($request->user(), $subCompany);

        return response()->json([
            'success' => true,
            'message' => __('operational.wallet.detail'),
            'data' => new OpsWalletResource($wallet->load(['mandor', 'subCompany'])),
        ]);
    }

    public function transactions(Request $request)
    {
        $subCompany = $this->resolveSubCompany($request);
        $wallet = $this->walletService->getOrCreateWallet($request->user(), $subCompany);

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

        return response()->json($this->dataTablesResponse($request, $transactions, [
            'success' => true,
            'message' => __('operational.wallet.transactions'),
            'data' => OpsWalletTransactionResource::collection($transactions),
        ]));
    }

    protected function resolveSubCompany(Request $request)
    {
        return $this->subCompanyService->resolveForMandorRequest(
            $request->query('sub_company_uuid'),
            $request->user()
        );
    }
}
