<?php

namespace App\Http\Controllers\Api\Absence;

use App\Http\Controllers\Controller;
use App\Http\Resources\Absence\AbsAttendanceResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\AbsAttendance;
use Illuminate\Http\Request;

class AbsAdminAttendanceController extends Controller
{
    use DataTablesResponse;

    public function index(Request $request)
    {
        $records = AbsAttendance::with(['user', 'subCompany', 'shift'])
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($request->sub_company_uuid, fn ($q, $uuid) =>
                $q->whereHas('subCompany', fn ($sc) => $sc->where('uuid', $uuid))
            )
            ->when($request->employee_uuid, fn ($q, $uuid) =>
                $q->whereHas('user', fn ($u) => $u->where('uuid', $uuid))
            )
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $records, [
                'success' => true,
                'message' => __('absence.attendance.list'),
                'data' => AbsAttendanceResource::collection($records),
            ])
        );
    }

    public function show(AbsAttendance $absAttendance)
    {
        return response()->json([
            'success' => true,
            'message' => __('absence.attendance.detail'),
            'data' => new AbsAttendanceResource(
                $absAttendance->load(['user', 'subCompany', 'shift'])
            ),
        ]);
    }
}
