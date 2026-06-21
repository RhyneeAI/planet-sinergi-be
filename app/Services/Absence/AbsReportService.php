<?php

namespace App\Services\Absence;

use App\Exports\AbsReportExport;
use App\Helpers\FileHelper;
use App\Models\AbsAttendance;
use App\Models\AbsBonus;
use App\Models\AbsDeduction;
use App\Models\AbsPayrollPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class AbsReportService
{
    public function attendanceQuery(Request $request): Builder
    {
        return AbsAttendance::with(['user', 'subCompany', 'shift'])
            ->when($request->date_from, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('date', '<=', $date))
            ->when(
                $request->sub_company_uuid,
                fn($q, $uuid) =>
                $q->whereHas('subCompany', fn($sc) => $sc->where('uuid', $uuid))
            )
            ->when(
                $request->employee_uuid,
                fn($q, $uuid) =>
                $q->whereHas('user', fn($u) => $u->where('uuid', $uuid))
            )
            ->orderByDesc('date');
    }

    public function payrollQuery(Request $request): Builder
    {
        $month = (int) $request->input('month', now(config('absence.timezone'))->month);
        $year = (int) $request->input('year', now(config('absence.timezone'))->year);

        return AbsPayrollPeriod::with(['user', 'deductions', 'bonuses'])
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->when(
                $request->employee_uuid,
                fn($q, $uuid) =>
                $q->whereHas('user', fn($u) => $u->where('uuid', $uuid))
            )
            ->orderBy('user_id');
    }

    public function deductionsQuery(Request $request): Builder
    {
        return AbsDeduction::with([
            'user.absEmployeeProfile.subCompany',
            'attendance.subCompany',
            'payrollPeriod',
            'createdBy',
        ])
            ->where(function (Builder $query) use ($request) {
                $query->whereHas('attendance', function (Builder $attendance) use ($request) {
                    $attendance
                        ->when($request->date_from, fn($q, $date) => $q->whereDate('date', '>=', $date))
                        ->when($request->date_to, fn($q, $date) => $q->whereDate('date', '<=', $date))
                        ->when(
                            $request->sub_company_uuid,
                            fn($q, $uuid) =>
                            $q->whereHas('subCompany', fn($sc) => $sc->where('uuid', $uuid))
                        )
                        ->when(
                            $request->employee_uuid,
                            fn($q, $uuid) =>
                            $q->whereHas('user', fn($u) => $u->where('uuid', $uuid))
                        );
                })->orWhere(function (Builder $query) use ($request) {
                    $query->whereNull('abs_attendance_id')
                        ->when($request->date_from, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($request->date_to, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
                        ->when(
                            $request->employee_uuid,
                            fn($q, $uuid) =>
                            $q->whereHas('user', fn($u) => $u->where('uuid', $uuid))
                        )
                        ->when(
                            $request->sub_company_uuid,
                            fn($q, $uuid) =>
                            $q->whereHas('user.absEmployeeProfile.subCompany', fn($sc) => $sc->where('uuid', $uuid))
                        );
                });
            })
            ->orderByDesc('created_at');
    }

    public function bonusesQuery(Request $request): Builder
    {
        return AbsBonus::with([
            'user.absEmployeeProfile.subCompany',
            'payrollPeriod',
            'createdBy',
        ])
            ->when($request->date_from, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when(
                $request->sub_company_uuid,
                fn($q, $uuid) =>
                $q->whereHas('user.absEmployeeProfile.subCompany', fn($sc) => $sc->where('uuid', $uuid))
            )
            ->when(
                $request->employee_uuid,
                fn($q, $uuid) =>
                $q->whereHas('user', fn($u) => $u->where('uuid', $uuid))
            )
            ->orderByDesc('created_at');
    }

    public function employeesQuery(Request $request): Builder
    {
        return User::with([
            'absEmployeeProfile.jabatan',
            'absEmployeeProfile.subCompany',
            'absEmployeeProfile.shift'
        ])
            ->when($request->jabatan_uuid, function ($q, $uuid) {
                $q->whereHas('absEmployeeProfile.jabatan', function ($p0) use ($uuid) {
                    $p0->where('uuid', $uuid);
                });
            })
            ->when($request->sub_company_uuid, function ($q, $uuid) {
                $q->whereHas('absEmployeeProfile.subCompany', function ($p0) use ($uuid) {
                    $p0->where('uuid', $uuid);
                });
            })
            ->orderBy('name', 'ASC');
    }

    public function isExportMode(Request $request): bool
    {
        return $request->input('mode') === 'export';
    }

    public function storeXlsxExport(Request $request, string $subfolder, string $filename, array $headers, Collection $rows): array
    {
        $storagePath = 'reports/' . $subfolder . '/' . $filename;

        FileHelper::saveExcel(new AbsReportExport($headers, $rows), $storagePath);

        return FileHelper::exportResponse($storagePath, $filename, $rows->count());
    }

    public function formatStatusLabel(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        return Str::title(str_replace('_', ' ', $value));
    }

    public function attendanceExportRows(Collection $records): Collection
    {
        return $records->map(fn(AbsAttendance $record, $index) => [
            $index + 1,
            $record->date?->toDateString(),
            $record->user?->name,
            $record->subCompany?->name,
            $record->shift?->name,
            $this->formatStatusLabel($record->status?->value),
            $record->check_in_time ? substr((string) $record->check_in_time, 0, 8) : '',
            $record->check_out_time ? substr((string) $record->check_out_time, 0, 8) : '',
            $record->late_reason,
            $record->early_reason,
        ]);
    }

    public function payrollExportRows(Collection $records): Collection
    {
        return $records->map(fn(AbsPayrollPeriod $record, $index) => [
            $index + 1,
            sprintf('%02d/%d', $record->period_month, $record->period_year),
            $record->user?->name,
            (float) $record->daily_rate,
            (int) $record->total_days,
            (float) $record->gross_salary,
            (float) $record->total_bonus,
            (float) $record->total_deduction,
            (float) $record->net_salary,
            $this->formatStatusLabel($record->status?->value),
        ]);
    }

    public function bonusesExportRows(Collection $records): Collection
    {
        return $records->map(function (AbsBonus $record, $index) {
            $subCompany = $record->user?->absEmployeeProfile?->subCompany;

            return [
                $index + 1,
                $record->created_at?->toDateString(),
                $record->user?->name,
                $subCompany?->name,
                $record->payrollPeriod
                    ? sprintf('%02d/%d', $record->payrollPeriod->period_month, $record->payrollPeriod->period_year)
                    : '',
                $record->reason,
                (float) $record->amount,
                $record->createdBy?->name,
            ];
        });
    }

    public function deductionsExportRows(Collection $records): Collection
    {
        return $records->map(function (AbsDeduction $record, $index) {
            $subCompany = $record->attendance?->subCompany
                ?? $record->user?->absEmployeeProfile?->subCompany;

            return [
                $index + 1,
                $record->attendance?->date?->toDateString() ?? $record->created_at?->toDateString(),
                $record->user?->name,
                $subCompany?->name,
                $record->payrollPeriod
                    ? sprintf('%02d/%d', $record->payrollPeriod->period_month, $record->payrollPeriod->period_year)
                    : '',
                $record->reason,
                (float) $record->amount,
                $record->createdBy?->name,
            ];
        });
    }

    public function employeesExportRows(Collection $records): Collection
    {
        return $records->map(function (User $record, $index) {
            $employeeProfile = $record->absEmployeeProfile;

            return [
                $index + 1,
                $record->name,
                $record->phone,
                $employeeProfile?->subCompany?->name,
                $employeeProfile?->jabatan?->name,
                $record->is_active ? 'Aktif' : 'Tidak Aktif',
                $employeeProfile?->shift?->name
            ];
        });
    }
}
