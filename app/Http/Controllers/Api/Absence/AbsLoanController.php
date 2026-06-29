<?php

namespace App\Http\Controllers\Api\Absence;

use App\Enums\AbsLoanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\AbsLoanRequest;
use App\Http\Resources\Absence\AbsLoanResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\AbsLoan;
use Illuminate\Http\Request;

class AbsLoanController extends Controller
{
    use DataTablesResponse;

    public function index(Request $request)
    {
        $loans = AbsLoan::with('user')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->user_uuid, fn($q, $uuid) => $q->whereHas('user', fn($uq) => $uq->where('uuid', $uuid)))
            ->orderBy($request->input('order_by', 'created_at'), $request->input('order_by_value', 'DESC'))
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $loans, [
                'success' => true,
                'message' => __('absence.loans.list'),
                'data' => AbsLoanResource::collection($loans),
            ])
        );
    }

    public function store(AbsLoanRequest $request)
    {
        $data = $request->validated();
        $amount = (float) $data['amount'];
        $tenor = (int) $data['tenor_months'];
        $monthlyInstallment = round($amount / $tenor, 2);
        $userId = \App\Models\User::where('uuid', $data['user_uuid'])->value('id');

        $loan = AbsLoan::create([
            'user_id' => $userId,
            'amount' => $amount,
            'reason' => $data['reason'],
            'tenor_months' => $tenor,
            'monthly_installment' => $monthlyInstallment,
            'remaining_balance' => $amount,
            'status' => AbsLoanStatus::PENDING,
            'company_id' => $request->user()->company_id,
        ]);

        $loan->load('user');

        return response()->json([
            'success' => true,
            'message' => __('absence.loans.stored'),
            'data' => new AbsLoanResource($loan),
        ], 201);
    }

    public function show(AbsLoan $absLoan)
    {
        $absLoan->load('user', 'approver');

        return response()->json([
            'success' => true,
            'message' => __('absence.loans.detail'),
            'data' => new AbsLoanResource($absLoan),
        ]);
    }

    public function approve($id)
    {
        $loan = AbsLoan::findOrFail($id);

        if ($loan->status !== AbsLoanStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('absence.loans.already_processed'),
            ], 422);
        }

        $loan->update([
            'status' => AbsLoanStatus::APPROVED,
            'approved_by' => auth()->id(),
        ]);

        $loan->load('user', 'approver');

        return response()->json([
            'success' => true,
            'message' => __('absence.loans.approved'),
            'data' => new AbsLoanResource($loan),
        ]);
    }

    public function reject($id)
    {
        $loan = AbsLoan::findOrFail($id);

        if ($loan->status !== AbsLoanStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('absence.loans.already_processed'),
            ], 422);
        }

        $loan->update([
            'status' => AbsLoanStatus::REJECTED,
            'approved_by' => auth()->id(),
        ]);

        $loan->load('user', 'approver');

        return response()->json([
            'success' => true,
            'message' => __('absence.loans.rejected'),
            'data' => new AbsLoanResource($loan),
        ]);
    }
}
