<?php

namespace App\Http\Controllers\Api\Absence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\AbsShiftRequest;
use App\Http\Resources\Absence\AbsShiftResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\AbsShift;
use Illuminate\Http\Request;

class AbsShiftController extends Controller
{
    use DataTablesResponse;

    public function index(Request $request)
    {
        $orderByKey = $request->input('order_by', 'name');
        $orderByValue = $request->input('order_by_value', 'ASC');

        $shifts = AbsShift::when($request->search, function ($query, $search) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $shifts, [
                'success' => true,
                'message' => __('absence.shifts.list'),
                'data' => AbsShiftResource::collection($shifts),
            ])
        );
    }

    public function store(AbsShiftRequest $request)
    {
        $shift = AbsShift::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('absence.shifts.stored'),
            'data' => new AbsShiftResource($shift),
        ], 201);
    }

    public function show(AbsShift $absShift)
    {
        return response()->json([
            'success' => true,
            'message' => __('absence.shifts.detail'),
            'data' => new AbsShiftResource($absShift),
        ]);
    }

    public function update(AbsShiftRequest $request, AbsShift $absShift)
    {
        $absShift->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => __('absence.shifts.updated'),
            'data' => new AbsShiftResource($absShift->fresh()),
        ]);
    }

    public function destroy(AbsShift $absShift)
    {
        $absShift->delete();

        return response()->json([
            'success' => true,
            'message' => __('absence.shifts.deleted'),
        ]);
    }
}
