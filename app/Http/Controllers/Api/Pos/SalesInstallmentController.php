<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\PosInstallmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\InstallmentPaymentRequest;
use App\Http\Resources\Pos\SalesInstallmentPlanResource;
use App\Models\PosSalesInstallmentPlan;
use App\Services\Pos\PosInstallmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalesInstallmentController extends Controller
{
    public function __construct(
        protected PosInstallmentService $installmentService,
    ) {}

    public function index(Request $request)
    {
        $plans = PosSalesInstallmentPlan::with(['customer', 'salesTransaction', 'salesTransaction.createdBy'])
            ->when($request->status, fn($q, $status) =>
                $q->where('status', $status)
            )
            ->when($request->search, fn($q, $search) =>
                $q->whereHas('customer', fn($c) =>
                    $c->where('name', 'like', "%{$search}%")
                )
            )
            ->when($request->created_by_uuid, fn($q, $uuid) =>
                $q->whereHas('salesTransaction.createdBy', fn($u) =>
                    $u->where('uuid', $uuid)
                )
            )
            ->orderBy('created_at', 'DESC')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('installments.list'),
            'data'    => SalesInstallmentPlanResource::collection($plans),
        ]);
    }

    public function show(PosSalesInstallmentPlan $salesInstallmentPlan)
    {
        return response()->json([
            'success' => true,
            'message' => __('installments.detail'),
            'data'    => new SalesInstallmentPlanResource(
                $salesInstallmentPlan->load(['customer', 'salesTransaction', 'salesTransaction.createdBy', 'payments'])
            ),
        ]);
    }

    public function pay(InstallmentPaymentRequest $request, PosSalesInstallmentPlan $salesInstallmentPlan)
    {
        try {
            $plan = $this->installmentService->paySales(
                plan: $salesInstallmentPlan,
                paidAmount: (float) $request->paid_amount,
                notes: $request->notes,
                user: $request->user(),
            );

            $isCompleted = $plan->status === PosInstallmentStatus::COMPLETED;

            return response()->json([
                'success' => true,
                'message' => $isCompleted
                    ? __('installments.completed')
                    : __('installments.payment_recorded'),
                'data'    => new SalesInstallmentPlanResource($plan),
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
