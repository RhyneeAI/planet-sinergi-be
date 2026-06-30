<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\PosInstallmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosInstallmentPaymentRequest;
use App\Http\Resources\Pos\PosSalesInstallmentPlanResource;
use App\Models\PosSalesInstallmentPlan;
use App\Services\Pos\PosInstallmentService;
use App\Http\Traits\DataTablesResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosSalesInstallmentController extends Controller
{
    use DataTablesResponse;

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

        return response()->json(
            $this->dataTablesResponse($request, $plans, [
                'success' => true,
                'message' => __('pos.installments.list'),
                'data'    => PosSalesInstallmentPlanResource::collection($plans),
            ])
        );
    }

    public function show(PosSalesInstallmentPlan $salesInstallmentPlan)
    {
        return response()->json([
            'success' => true,
            'message' => __('pos.installments.detail'),
            'data'    => new PosSalesInstallmentPlanResource(
                $salesInstallmentPlan->load(['customer', 'salesTransaction', 'salesTransaction.createdBy', 'payments'])
            ),
        ]);
    }

    public function pay(PosInstallmentPaymentRequest $request, PosSalesInstallmentPlan $salesInstallmentPlan)
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
                    ? __('pos.installments.completed')
                    : __('pos.installments.payment_recorded'),
                'data'    => new PosSalesInstallmentPlanResource($plan),
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
