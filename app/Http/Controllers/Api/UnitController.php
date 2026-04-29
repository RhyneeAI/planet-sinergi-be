<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = Unit::orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('unit.list'),
            'data'    => UnitResource::collection($units),
        ]);
    }

    public function store(UnitRequest $request)
    {
        $unit = Unit::create([
            'name'       => $request->name,
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('unit.stored'),
            'data'    => new UnitResource($unit),
        ], 201);
    }

    public function show(Unit $unit)
    {
        return response()->json([
            'success' => true,
            'message' => __('unit.detail'),
            'data'    => new UnitResource($unit),
        ]);
    }

    public function update(UnitRequest $request, Unit $unit)
    {
        $unit->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => __('unit.updated'),
            'data'    => new UnitResource($unit),
        ]);
    }

    public function destroy(Unit $unit)
    {
        if ($unit->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('unit.has_products'),
                'code'    => 422,
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => __('unit.deleted'),
        ]);
    }
}