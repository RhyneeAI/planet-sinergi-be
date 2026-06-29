<?php

namespace App\Http\Controllers\Api\Absence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\AbsBonusRequest;
use App\Http\Requests\Absence\AbsDeductionRequest;
use App\Http\Resources\Absence\AbsBonusResource;
use App\Http\Resources\Absence\AbsDeductionResource;
use App\Http\Resources\Absence\AbsPayrollPeriodResource;
use App\Models\AbsAttendance;
use App\Models\AbsBonus;
use App\Models\AbsDeduction;
use App\Models\AbsPayrollPeriod;
use App\Http\Traits\DataTablesResponse;
use App\Services\Absence\AbsPayrollService;
use Illuminate\Http\Request;

class AbsPayrollController extends Controller
{
    use DataTablesResponse;

    public function __construct(
        protected AbsPayrollService $payrollService,
    ) {}

    public function index(Request $request)
    {
        $orderByKey = $request->input('order_by', 'user_id');
        $orderByValue = $request->input('order_by_value', 'DESC');

        $month = (int) $request->input('month', now(config('absence.timezone'))->month);
        $year = (int) $request->input('year', now(config('absence.timezone'))->year);

        $records = AbsPayrollPeriod::with(['user', 'deductions', 'bonuses'])
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $records, [
                'success' => true,
                'message' => __('absence.payroll.list'),
                'data' => AbsPayrollPeriodResource::collection($records),
            ])
        );
    }

    public function generate(Request $request)
    {
        $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020'],
        ]);

        $count = $this->payrollService->generateForCompany(
            $request->user()->company_id,
            (int) $request->month,
            (int) $request->year
        );

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.generated'),
            'data' => ['generated_count' => $count],
        ]);
    }

    public function show(AbsPayrollPeriod $absPayrollPeriod)
    {
        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.detail'),
            'data' => new AbsPayrollPeriodResource(
                $absPayrollPeriod->load(['user', 'deductions', 'bonuses'])
            ),
        ]);
    }

    public function storeDeduction(AbsDeductionRequest $request, AbsPayrollPeriod $absPayrollPeriod)
    {
        try {
            $attendanceId = null;

            if ($request->attendance_ulid) {
                $attendanceId = AbsAttendance::where('ulid', $request->attendance_ulid)->value('id');
            }

            $deduction = $this->payrollService->addDeduction(
                $absPayrollPeriod,
                $request->user(),
                $request->reason,
                (float) $request->amount,
                $attendanceId
            );

            $this->payrollService->recalculate($absPayrollPeriod);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.deduction_stored'),
            'data' => new AbsDeductionResource($deduction->load('createdBy')),
        ], 201);
    }

    public function updateDeduction(
        AbsDeductionRequest $request,
        AbsPayrollPeriod $absPayrollPeriod,
        AbsDeduction $absDeduction
    ) {
        $this->ensureDeductionBelongsToPeriod($absPayrollPeriod, $absDeduction);

        try {
            $attendanceId = $absDeduction->abs_attendance_id;

            if ($request->attendance_ulid) {
                $attendanceId = AbsAttendance::where('ulid', $request->attendance_ulid)->value('id');
            }

            $deduction = $this->payrollService->updateDeduction($absDeduction, [
                'reason' => $request->reason,
                'amount' => $request->amount,
                'abs_attendance_id' => $attendanceId,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.deduction_updated'),
            'data' => new AbsDeductionResource($deduction->load('createdBy')),
        ]);
    }

    public function destroyDeduction(AbsPayrollPeriod $absPayrollPeriod, AbsDeduction $absDeduction)
    {
        $this->ensureDeductionBelongsToPeriod($absPayrollPeriod, $absDeduction);

        try {
            $this->payrollService->deleteDeduction($absDeduction);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.deduction_deleted'),
        ]);
    }

    public function storeBonus(AbsBonusRequest $request, AbsPayrollPeriod $absPayrollPeriod)
    {
        try {
            $bonus = $this->payrollService->addBonus(
                $absPayrollPeriod,
                $request->user(),
                $request->reason,
                (float) $request->amount,
            );

            $this->payrollService->recalculate($absPayrollPeriod);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.bonus_stored'),
            'data' => new AbsBonusResource($bonus->load('createdBy')),
        ], 201);
    }

    public function updateBonus(
        AbsBonusRequest $request,
        AbsPayrollPeriod $absPayrollPeriod,
        AbsBonus $absBonus
    ) {
        $this->ensureBonusBelongsToPeriod($absPayrollPeriod, $absBonus);

        try {
            $bonus = $this->payrollService->updateBonus($absBonus, [
                'reason' => $request->reason,
                'amount' => $request->amount,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.bonus_updated'),
            'data' => new AbsBonusResource($bonus->load('createdBy')),
        ]);
    }

    public function destroyBonus(AbsPayrollPeriod $absPayrollPeriod, AbsBonus $absBonus)
    {
        $this->ensureBonusBelongsToPeriod($absPayrollPeriod, $absBonus);

        try {
            $this->payrollService->deleteBonus($absBonus);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.bonus_deleted'),
        ]);
    }

    public function finalize(AbsPayrollPeriod $absPayrollPeriod)
    {
        try {
            $period = $this->payrollService->finalize($absPayrollPeriod);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.finalized'),
            'data' => new AbsPayrollPeriodResource($period->load(['user', 'deductions', 'bonuses'])),
        ]);
    }

    public function unlock(AbsPayrollPeriod $absPayrollPeriod)
    {
        $period = $this->payrollService->unlock($absPayrollPeriod);

        return response()->json([
            'success' => true,
            'message' => __('absence.payroll.unlocked'),
            'data' => new AbsPayrollPeriodResource($period->load(['user', 'deductions', 'bonuses'])),
        ]);
    }

    protected function ensureDeductionBelongsToPeriod(
        AbsPayrollPeriod $period,
        AbsDeduction $deduction
    ): void {
        if ($deduction->abs_payroll_period_id !== $period->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'Not found.',
                'code' => 404,
            ], 404));
        }
    }

    protected function ensureBonusBelongsToPeriod(
        AbsPayrollPeriod $period,
        AbsBonus $bonus
    ): void {
        if ($bonus->abs_payroll_period_id !== $period->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'Not found.',
                'code' => 404,
            ], 404));
        }
    }
}
