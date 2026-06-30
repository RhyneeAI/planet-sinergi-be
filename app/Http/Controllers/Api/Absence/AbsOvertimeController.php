<?php

namespace App\Http\Controllers\Api\Absence;

use App\Enums\AbsOvertimeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\AbsOvertimeRejectRequest;
use App\Http\Requests\Absence\AbsOvertimeRequest;
use App\Http\Resources\Absence\AbsOvertimeResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\AbsOvertime;
use Illuminate\Http\Request;

class AbsOvertimeController extends Controller
{
    use DataTablesResponse;

    public function index(Request $request)
    {
        $overtimes = AbsOvertime::with('user')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->date, fn($q, $d) => $q->whereDate('date', $d))
            ->when($request->user_uuid, fn($q, $uuid) => $q->whereHas('user', fn($uq) => $uq->where('uuid', $uuid)))
            ->orderBy($request->input('order_by', 'date'), $request->input('order_by_value', 'DESC'))
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $overtimes, [
                'success' => true,
                'message' => __('absence.overtimes.list'),
                'data' => AbsOvertimeResource::collection($overtimes),
            ])
        );
    }

    public function store(AbsOvertimeRequest $request)
    {
        $companyId = $request->user()->company_id;
        $data = $request->validated();
        $userIds = \App\Models\User::whereIn('uuid', $data['user_uuids'])->pluck('id');
        $baseData = [
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'reason' => $data['reason'],
            'status' => AbsOvertimeStatus::PENDING,
            'company_id' => $companyId,
        ];

        $overtimes = $userIds->map(fn($userId) => AbsOvertime::create([
            ...$baseData,
            'user_id' => $userId,
        ]));

        return response()->json([
            'success' => true,
            'message' => __('absence.overtimes.stored'),
            'data' => AbsOvertimeResource::collection($overtimes),
        ], 201);
    }

    public function show(AbsOvertime $absOvertime)
    {
        $absOvertime->load('user', 'approver');

        return response()->json([
            'success' => true,
            'message' => __('absence.overtimes.detail'),
            'data' => new AbsOvertimeResource($absOvertime),
        ]);
    }

    public function approve($id)
    {
        $overtime = AbsOvertime::findOrFail($id);

        if ($overtime->status !== AbsOvertimeStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('absence.overtimes.already_processed'),
            ], 422);
        }

        $overtime->update([
            'status' => AbsOvertimeStatus::APPROVED,
            'approved_by' => auth()->id(),
        ]);

        $overtime->load('user', 'approver');

        return response()->json([
            'success' => true,
            'message' => __('absence.overtimes.approved'),
            'data' => new AbsOvertimeResource($overtime),
        ]);
    }

    public function reject(AbsOvertimeRejectRequest $request, $id)
    {
        $overtime = AbsOvertime::findOrFail($id);

        if ($overtime->status !== AbsOvertimeStatus::PENDING) {
            return response()->json([
                'success' => false,
                'message' => __('absence.overtimes.already_processed'),
            ], 422);
        }

        $overtime->update([
            'status' => AbsOvertimeStatus::REJECTED,
            'approved_by' => auth()->id(),
        ]);

        $overtime->load('user', 'approver');

        return response()->json([
            'success' => true,
            'message' => __('absence.overtimes.rejected'),
            'data' => new AbsOvertimeResource($overtime),
        ]);
    }
}
