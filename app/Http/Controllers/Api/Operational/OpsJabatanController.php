<?php

namespace App\Http\Controllers\Api\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\OpsJabatanRequest;
use App\Http\Resources\Absence\AbsJabatanResource;
use App\Models\AbsJabatan;
use App\Models\OpsEditLog;
use Illuminate\Http\Request;

class OpsJabatanController extends Controller
{
    public function index(Request $request)
    {
        $orderByKey = $request->input('order_by', 'name');
        $orderByValue = $request->input('order_by_value', 'ASC');

        $jabatans = AbsJabatan::when($request->search, function ($query, $search) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.jabatans.list'),
            'data' => AbsJabatanResource::collection($jabatans),
        ]);
    }

    public function store(OpsJabatanRequest $request)
    {
        $jabatan = AbsJabatan::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('operational.jabatans.stored'),
            'data' => new AbsJabatanResource($jabatan),
        ], 201);
    }

    public function show(AbsJabatan $absJabatan)
    {
        return response()->json([
            'success' => true,
            'message' => __('operational.jabatans.detail'),
            'data' => new AbsJabatanResource($absJabatan),
        ]);
    }

    public function update(OpsJabatanRequest $request, AbsJabatan $absJabatan)
    {
        $original = $absJabatan->replicate();
        $absJabatan->update($request->validated());

        $originalDailyRate = (float) $original->daily_rate;
        $newDailyRate = (float) $absJabatan->fresh()->daily_rate;

        if ($originalDailyRate !== $newDailyRate) {
            OpsEditLog::create([
                'loggable_type' => 'abs_jabatans',
                'loggable_id' => $absJabatan->id,
                'reason' => $request->input('reason', 'Update salary jabatan'),
                'old_data' => ['daily_rate' => $originalDailyRate],
                'new_data' => ['daily_rate' => $newDailyRate],
                'edited_by' => auth()->id(),
                'company_id' => $request->user()->company_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('operational.jabatans.updated'),
            'data' => new AbsJabatanResource($absJabatan->fresh()),
        ]);
    }

    public function destroy(AbsJabatan $absJabatan)
    {
        if ($absJabatan->employeeProfiles()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('operational.jabatans.in_use'),
                'code' => 422,
            ], 422);
        }

        $absJabatan->delete();

        return response()->json([
            'success' => true,
            'message' => __('operational.jabatans.deleted'),
        ]);
    }
}
