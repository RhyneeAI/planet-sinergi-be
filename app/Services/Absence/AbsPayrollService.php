<?php

namespace App\Services\Absence;

use App\Enums\AbsLoanStatus;
use App\Enums\AbsPayrollStatus;
use App\Enums\Role;
use App\Models\AbsAttendance;
use App\Models\AbsBonus;
use App\Models\AbsDeduction;
use App\Models\AbsEmployeeProfile;
use App\Models\AbsLoan;
use App\Models\AbsPayrollPeriod;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AbsPayrollService
{
    public function getOrGenerateForUser(User $user, int $month, int $year): AbsPayrollPeriod
    {
        $existing = AbsPayrollPeriod::where('user_id', $user->id)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->first();

        if ($existing) {
            return $this->recalculate($existing);
        }

        $profile = $user->absEmployeeProfile?->load('position');

        if (!$profile) {
            throw new \RuntimeException(__('absence.payroll.profile_not_found'));
        }

        if (!$profile->position) {
            throw new \RuntimeException(__('absence.payroll.position_not_assigned'));
        }

        $period = AbsPayrollPeriod::create([
            'user_id' => $user->id,
            'period_month' => $month,
            'period_year' => $year,
            'daily_rate' => $profile->position->daily_rate,
            'status' => AbsPayrollStatus::DRAFT,
            'generated_at' => now(),
            'company_id' => $user->company_id,
        ]);

        return $this->recalculate($period);
    }

    public function generateForCompany(int $companyId, int $month, int $year): int
    {
        $profiles = AbsEmployeeProfile::with(['user', 'position'])
            ->where('company_id', $companyId)
            ->get();

        $count = 0;

        foreach ($profiles as $profile) {
            $user = $profile->user;

            if (!$user?->is_active || !$profile->position) {
                continue;
            }

            if (in_array($user->role->value, [Role::MARKETING->value, Role::MARKETING_TETAP->value])) {
                continue;
            }

            $this->getOrGenerateForUser($user, $month, $year);
            $count++;
        }

        return $count;
    }

    public function recalculate(AbsPayrollPeriod $period): AbsPayrollPeriod
    {
        if ($period->isFinal()) {
            return $period;
        }

        $totalDays = AbsAttendance::where('user_id', $period->user_id)
            ->whereYear('date', $period->period_year)
            ->whereMonth('date', $period->period_month)
            ->whereIn('status', config('absence.attended_statuses'))
            ->count();

        $gross = round((float) $period->daily_rate * $totalDays, 2);
        $deduction = (float) $period->deductions()->sum('amount');
        $bonus = (float) $period->bonuses()->sum('amount');
        $net = round($gross + $bonus - $deduction, 2);

        $period->update([
            'total_days' => $totalDays,
            'gross_salary' => $gross,
            'total_deduction' => $deduction,
            'total_bonus' => $bonus,
            'net_salary' => $net,
        ]);

        return $period->fresh(['deductions', 'bonuses', 'user.absEmployeeProfile.subCompany', 'user.absEmployeeProfile.shift']);
    }

    public function addDeduction(
        AbsPayrollPeriod $period,
        User $admin,
        string $reason,
        float $amount,
        ?int $attendanceId = null
    ): AbsDeduction {
        if ($period->isFinal()) {
            throw new \RuntimeException(__('absence.payroll.already_final'));
        }

        $deduction = AbsDeduction::create([
            'abs_payroll_period_id' => $period->id,
            'user_id' => $period->user_id,
            'abs_attendance_id' => $attendanceId,
            'reason' => $reason,
            'amount' => $amount,
            'created_by' => $admin->id,
            'company_id' => $period->company_id,
        ]);

        return $deduction;
    }

    public function updateDeduction(AbsDeduction $deduction, array $data): AbsDeduction
    {
        if ($deduction->payrollPeriod->isFinal()) {
            throw new \RuntimeException(__('absence.payroll.already_final'));
        }

        $deduction->update($data);

        $this->recalculate($deduction->payrollPeriod);

        return $deduction->fresh();
    }

    public function deleteDeduction(AbsDeduction $deduction): void
    {
        if ($deduction->payrollPeriod->isFinal()) {
            throw new \RuntimeException(__('absence.payroll.already_final'));
        }

        $period = $deduction->payrollPeriod;
        $deduction->delete();
        $this->recalculate($period);
    }

    public function addBonus(
        AbsPayrollPeriod $period,
        User $admin,
        string $reason,
        float $amount,
    ): AbsBonus {
        if ($period->isFinal()) {
            throw new \RuntimeException(__('absence.payroll.already_final'));
        }

        $bonus = AbsBonus::create([
            'abs_payroll_period_id' => $period->id,
            'user_id' => $period->user_id,
            'reason' => $reason,
            'amount' => $amount,
            'created_by' => $admin->id,
            'company_id' => $period->company_id,
        ]);

        return $bonus;
    }

    public function updateBonus(AbsBonus $bonus, array $data): AbsBonus
    {
        if ($bonus->payrollPeriod->isFinal()) {
            throw new \RuntimeException(__('absence.payroll.already_final'));
        }

        $bonus->update($data);

        $this->recalculate($bonus->payrollPeriod);

        return $bonus->fresh();
    }

    public function deleteBonus(AbsBonus $bonus): void
    {
        if ($bonus->payrollPeriod->isFinal()) {
            throw new \RuntimeException(__('absence.payroll.already_final'));
        }

        $period = $bonus->payrollPeriod;
        $bonus->delete();
        $this->recalculate($period);
    }

    public function finalize(AbsPayrollPeriod $period): AbsPayrollPeriod
    {
        if ($period->isFinal()) {
            throw new \RuntimeException(__('absence.payroll.already_final'));
        }

        $this->applyLoanInstallments($period);

        $period = $this->recalculate($period);
        $period->update(['status' => AbsPayrollStatus::FINAL]);

        return $period->fresh(['deductions', 'bonuses', 'user']);
    }

    private function applyLoanInstallments(AbsPayrollPeriod $period): void
    {
        $loanPrefix = 'LOAN#';
        $activeLoans = AbsLoan::where('user_id', $period->user_id)
            ->where('status', AbsLoanStatus::APPROVED)
            ->where('remaining_balance', '>', 0)
            ->get();

        foreach ($activeLoans as $loan) {
            $installment = (float) $loan->monthly_installment;
            $remaining = (float) $loan->remaining_balance;

            $deductionAmount = min($installment, $remaining);

            $alreadyDeducted = AbsDeduction::where('abs_payroll_period_id', $period->id)
                ->where('user_id', $period->user_id)
                ->where('reason', $loanPrefix . $loan->id)
                ->exists();

            if ($alreadyDeducted) {
                continue;
            }

            AbsDeduction::create([
                'abs_payroll_period_id' => $period->id,
                'user_id' => $period->user_id,
                'reason' => $loanPrefix . $loan->id,
                'amount' => $deductionAmount,
                'created_by' => $period->user_id,
                'company_id' => $period->company_id,
            ]);

            $newRemaining = round($remaining - $deductionAmount, 2);

            $loan->update([
                'remaining_balance' => $newRemaining,
                'status' => $newRemaining <= 0 ? AbsLoanStatus::PAID : AbsLoanStatus::APPROVED,
            ]);
        }
    }

    public function unlock(AbsPayrollPeriod $period): AbsPayrollPeriod
    {
        $this->removeLoanInstallments($period);

        $period->update(['status' => AbsPayrollStatus::DRAFT]);

        return $period->fresh(['deductions', 'bonuses', 'user']);
    }

    private function removeLoanInstallments(AbsPayrollPeriod $period): void
    {
        $loanPrefix = 'LOAN#';
        $loanDeductions = AbsDeduction::where('abs_payroll_period_id', $period->id)
            ->where('user_id', $period->user_id)
            ->where('reason', 'like', $loanPrefix . '%')
            ->get();

        foreach ($loanDeductions as $deduction) {
            $loanId = (int) str_replace($loanPrefix, '', $deduction->reason);
            $loan = AbsLoan::find($loanId);
            if ($loan) {
                $loan->increment('remaining_balance', (float) $deduction->amount);
                $loan->update(['status' => AbsLoanStatus::APPROVED->value]);
            }
            $deduction->delete();
        }
    }

    public function generateSlipPdf(AbsPayrollPeriod $period)
    {
        $period->load([
            'user.absEmployeeProfile.subCompany',
            'user.absEmployeeProfile.shift',
            'user.absEmployeeProfile.position',
            'deductions',
            'bonuses',
        ]);

        $attendances = AbsAttendance::where('user_id', $period->user_id)
            ->whereYear('date', $period->period_year)
            ->whereMonth('date', $period->period_month)
            ->whereIn('status', config('absence.attended_statuses'))
            ->orderBy('date')
            ->get();

        return Pdf::loadView('pdf.abs-payroll-slip', [
            'period' => $period,
            'attendances' => $attendances,
        ])->setPaper('a4');
    }

    public function currentPeriodPreview(User $user): array
    {
        $now = Carbon::now(config('absence.timezone'));
        $period = $this->getOrGenerateForUser($user, (int) $now->month, (int) $now->year);

        return [
            'ulid' => (string) $period->ulid,
            'period_month' => $period->period_month,
            'period_year' => $period->period_year,
            'daily_rate' => (float) $period->daily_rate,
            'total_days' => (int) $period->total_days,
            'gross_salary' => (float) $period->gross_salary,
            'total_deduction' => (float) $period->total_deduction,
            'net_salary' => (float) $period->net_salary,
            'status' => $period->status->value,
            'total_bonus' => (float) $period->total_bonus,
            'deductions' => $period->deductions->map(fn($d) => [
                'ulid' => (string) $d->ulid,
                'reason' => $d->reason,
                'amount' => (float) $d->amount,
            ])->values(),
            'bonuses' => $period->bonuses->map(fn($b) => [
                'ulid' => (string) $b->ulid,
                'reason' => $b->reason,
                'amount' => (float) $b->amount,
            ])->values(),
        ];
    }
}
