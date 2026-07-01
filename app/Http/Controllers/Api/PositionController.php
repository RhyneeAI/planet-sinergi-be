<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PositionRequest;
use App\Http\Resources\PositionResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\OpsEditLog;
use App\Models\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    use DataTablesResponse;

    public function index(Request $request)
    {
        $orderByKey = $request->input('order_by', 'name');
        $orderByValue = $request->input('order_by_value', 'ASC');

        $positions = Position::when($request->search, function ($query, $search) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $positions, [
                'success' => true,
                'message' => __('position.list'),
                'data' => PositionResource::collection($positions),
            ])
        );
    }

    public function store(PositionRequest $request)
    {
        $position = Position::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('position.stored'),
            'data' => new PositionResource($position),
        ], 201);
    }

    public function show(Position $position)
    {
        return response()->json([
            'success' => true,
            'message' => __('position.detail'),
            'data' => new PositionResource($position),
        ]);
    }

    public function update(PositionRequest $request, Position $position)
    {
        $original = $position->replicate();
        $position->update($request->validated());

        $originalDailyRate = (float) $original->daily_rate;
        $newDailyRate = (float) $position->fresh()->daily_rate;

        if ($originalDailyRate !== $newDailyRate) {
            OpsEditLog::create([
                'loggable_type' => 'positions',
                'loggable_id' => $position->id,
                'reason' => $request->input('reason', 'Update salary position'),
                'old_data' => ['daily_rate' => $originalDailyRate],
                'new_data' => ['daily_rate' => $newDailyRate],
                'edited_by' => auth()->id(),
                'company_id' => $request->user()->company_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('position.updated'),
            'data' => new PositionResource($position->fresh()),
        ]);
    }

    public function destroy(Position $position)
    {
        if ($position->employeeProfiles()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('position.in_use'),
                'code' => 422,
            ], 422);
        }

        $position->delete();

        return response()->json([
            'success' => true,
            'message' => __('position.deleted'),
        ]);
    }
}
