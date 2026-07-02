<?php

namespace App\Http\Controllers\Api\Absence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\AbsCheckInRequest;
use App\Http\Requests\Absence\AbsCheckOutRequest;
use App\Http\Resources\Absence\AbsAttendanceResource;
use App\Http\Resources\Absence\AbsShiftResource;
use App\Http\Resources\PositionResource;
use App\Http\Resources\SubCompanyResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\AbsAttendance;
use App\Services\Absence\AbsAttendanceService;
use Illuminate\Http\Request;

class AbsEmployeeAttendanceController extends Controller
{
    use DataTablesResponse;

    public function __construct(
        protected AbsAttendanceService $attendanceService,
    ) {}

    public function today(Request $request)
    {
        $user = $request->user();
        $profile = $user->absEmployeeProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => __('absence.attendance.profile_not_found'),
                'code' => 422,
            ], 422);
        }

        $profile->load(['subCompany', 'shift', 'position']);
        $attendance = $this->attendanceService->todayFor($user);
        $summary = $this->attendanceService->monthSummary($user);

        return response()->json([
            'success' => true,
            'message' => __('absence.attendance.today'),
            'data' => [
                'employee' => [
                    'name' => $user->name,
                    'position' => $profile->position ? new PositionResource($profile->position) : null,
                    'date' => now(config('absence.timezone'))->toDateString(),
                ],
                'shift' => $profile->shift ? new AbsShiftResource($profile->shift) : null,
                'sub_company' => $profile->subCompany ? new SubCompanyResource($profile->subCompany) : null,
                'attendance' => $attendance
                    ? new AbsAttendanceResource($attendance->load(['subCompany', 'shift']))
                    : null,
                'can_check_in' => !$attendance,
                'can_check_out' => $attendance && $attendance->hasCheckedIn() && !$attendance->hasCheckedOut(),
                'month_summary' => $summary,
            ],
        ]);
    }

    public function checkIn(AbsCheckInRequest $request)
    {
        $user = $request->user();
        $profile = $user->absEmployeeProfile?->load(['subCompany', 'shift']);

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => __('absence.attendance.profile_not_found'),
                'code' => 422,
            ], 422);
        }

        try {
            $attendance = $this->attendanceService->checkIn(
                $user,
                $profile,
                $request->file('photo'),
                (float) $request->latitude,
                (float) $request->longitude,
                $request->late_reason,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.attendance.check_in_success'),
            'data' => new AbsAttendanceResource($attendance->load(['subCompany', 'shift'])),
        ], 201);
    }

    public function checkOut(AbsCheckOutRequest $request)
    {
        $user = $request->user();
        $profile = $user->absEmployeeProfile?->load(['subCompany', 'shift']);

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => __('absence.attendance.profile_not_found'),
                'code' => 422,
            ], 422);
        }

        try {
            $attendance = $this->attendanceService->checkOut(
                $user,
                $profile,
                $request->file('photo'),
                (float) $request->latitude,
                (float) $request->longitude,
                $request->early_reason,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.attendance.check_out_success'),
            'data' => new AbsAttendanceResource($attendance->load(['subCompany', 'shift'])),
        ]);
    }

    public function history(Request $request)
    {
        $month = $request->input('month', now(config('absence.timezone'))->month);
        $year = $request->input('year', now(config('absence.timezone'))->year);

        $records = AbsAttendance::with(['subCompany', 'shift'])
            ->where('user_id', $request->user()->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderByDesc('date')
            ->paginate($request->input('per_page', 15));

        return response()->json($this->dataTablesResponse($request, $records, [
            'success' => true,
            'message' => __('absence.attendance.history'),
            'data' => AbsAttendanceResource::collection($records),
        ]));
    }
}
