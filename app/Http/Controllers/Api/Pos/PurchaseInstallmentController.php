<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\PosInstallmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\InstallmentPaymentRequest;
use App\Http\Resources\Pos\PurchaseInstallmentPlanResource;
use App\Models\PosPurchaseInstallmentPlan;
use App\Services\Pos\PosInstallmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseInstallmentController extends Controller
{
    public function __construct(
        protected PosInstallmentService $installmentService,
    ) {}

    public function index(Request $request)
    {
        $plans = PosPurchaseInstallmentPlan::with(['supplier', 'purchaseTransaction'])
            ->when($request->status, fn($q, $status) =>
                $q->where('status', $status)
            )
            ->when($request->search, fn($q, $search) =>
                $q->whereHas('supplier', fn($s) =>
                    $s->where('name', 'like', "%{$search}%")
                )
            )
            ->when($request->created_by_uuid, fn($q, $uuid) =>
                $q->whereHas('purchaseTransaction.createdBy', fn($u) =>
                    $u->where('uuid', $uuid)
                )
            )
            ->orderBy('created_at', 'DESC')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('installments.list'),
            'data'    => PurchaseInstallmentPlanResource::collection($plans),
        ]);
    }

    public function show(PosPurchaseInstallmentPlan $purchaseInstallmentPlan)
    {
        return response()->json([
            'success' => true,
            'message' => __('installments.detail'),
            'data'    => new PurchaseInstallmentPlanResource(
                $purchaseInstallmentPlan->load(['supplier', 'purchaseTransaction', 'payments'])
            ),
        ]);
    }

    public function pay(InstallmentPaymentRequest $request, PosPurchaseInstallmentPlan $purchaseInstallmentPlan)
    {
        try {
            $plan = $this->installmentService->payPurchase(
                plan: $purchaseInstallmentPlan,
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
                'data'    => new PurchaseInstallmentPlanResource($plan),
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
