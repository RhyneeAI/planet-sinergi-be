<?php

namespace App\Http\Controllers\Api\Absence;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AbsAttendance;
use App\Models\AbsEmployeeProfile;
use App\Models\SubCompany;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AbsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $today = Carbon::now(config('absence.timezone'))->toDateString();

        $activeEmployees = User::where('company_id', $companyId)
            ->where('role', Role::KARYAWAN)
            ->where('is_active', true)
            ->count();

        $todayAttendances = AbsAttendance::where('company_id', $companyId)
            ->whereDate('date', $today)
            ->get();

        $presentToday = $todayAttendances
            ->filter(fn($attendance) => $attendance->status?->countsForPayroll())
            ->count();
        $lateToday = $todayAttendances
            ->filter(fn($attendance) => in_array($attendance->status?->value, ['terlambat', 'terlambat_pulang_awal'], true))
            ->count();

        $checkedInIds = $todayAttendances->pluck('user_id')->unique();
        $notYetAbsent = User::where('company_id', $companyId)
            ->where('role', Role::KARYAWAN)
            ->where('is_active', true)
            ->whereNotIn('id', $checkedInIds)
            ->with(['absEmployeeProfile.subCompany'])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'uuid', 'name']);

        $subCompanies = SubCompany::where('company_id', $companyId)
            ->withCount(['employeeProfiles as employees_count'])
            ->get()
            ->map(function ($subCompany) use ($today, $companyId) {
                $employeeIds = AbsEmployeeProfile::where('sub_company_id', $subCompany->id)->pluck('user_id');
                $present = AbsAttendance::where('company_id', $companyId)
                    ->whereDate('date', $today)
                    ->whereIn('user_id', $employeeIds)
                    ->count();

                return [
                    'uuid' => (string) $subCompany->uuid,
                    'name' => $subCompany->name,
                    'employees_count' => (int) $subCompany->employees_count,
                    'present_today' => $present,
                ];
            });

        $period = $request->input('period', 'weekly');
        if ($period === 'monthly') {
            $startDate = Carbon::now(config('absence.timezone'))->startOfMonth();
            $endDate = Carbon::now(config('absence.timezone'))->endOfMonth();

            $attendanceChart = AbsAttendance::query()
                ->where('company_id', $companyId)
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw('DATE(date) as label, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'label');

            $chart = collect();

            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $label = $date->toDateString();

                $chart->push([
                    'label' => $date->format('d M'),
                    'date' => $label,
                    'present' => (int) ($attendanceChart[$label] ?? 0),
                ]);
            }
        } else {
            $startDate = Carbon::now(config('absence.timezone'))->startOfWeek();
            $endDate = Carbon::now(config('absence.timezone'))->endOfWeek();

            $attendanceChart = AbsAttendance::query()
                ->where('company_id', $companyId)
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw('DATE(date) as label, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'label');

            $chart = collect();

            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $label = $date->toDateString();

                $chart->push([
                    'label' => $date->translatedFormat('D'),
                    'date' => $label,
                    'present' => (int) ($attendanceChart[$label] ?? 0),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.dashboard.summary'),
            'data' => [
                'active_employees' => $activeEmployees,
                'present_today' => $presentToday,
                'late_today' => $lateToday,
                'not_yet_absent_count' => max(0, $activeEmployees - $checkedInIds->count()),
                'not_yet_absent' => $notYetAbsent->map(fn($u) => [
                    'uuid' => $u->uuid,
                    'name' => $u->name,
                    'sub_company' => $u->absEmployeeProfile?->subCompany?->name,
                ]),
                'sub_companies' => $subCompanies,
                'attendance_chart' => [
                    'period' => $period,
                    'items' => $chart,
                ],
            ],
        ]);
    }
}
