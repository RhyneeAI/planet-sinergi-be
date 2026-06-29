<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosUnitRequest;
use App\Http\Resources\Pos\PosUnitResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\PosUnit;
use Illuminate\Http\Request;

class PosUnitController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['name', 'created_at'];

    public function index(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
            ? $request->input('order_by_key', 'name')
            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $units = PosUnit::query()
            ->with(['createdBy'])
            ->when($request->search, function ($query, $search) {
                // Case-insensitive search using LOWER() for PostgreSQL and MySQL compatibility
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $units, [
                'success' => true,
                'message' => __('pos.units.list'),
                'data' => PosUnitResource::collection($units),
            ])
        );
    }

    public function store(PosUnitRequest $request)
    {
        $unit = PosUnit::create([
            'name'       => $request->name,
            'created_by' => $request->user()->id,
            'company_id' => $request->user()->company_id,
        ]);

        $unit->load('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.units.stored'),
            'data'    => new PosUnitResource($unit),
        ], 201);
    }

    public function show(PosUnit $unit)
    {
        $unit->loadMissing('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.units.detail'),
            'data'    => new PosUnitResource($unit),
        ]);
    }

    public function update(PosUnitRequest $request, PosUnit $unit)
    {
        if ($request->has('name')) {
            $unit->update(['name' => $request->name]);
        }

        $unit->load('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.units.updated'),
            'data'    => new PosUnitResource($unit),
        ]);
    }

    public function destroy(PosUnit $unit)
    {
        if ($unit->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('pos.units.has_products'),
                'code'    => 422,
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => __('pos.units.deleted'),
        ]);
    }
}
