<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosSupplierRequest;
use App\Http\Resources\Pos\PosSupplierResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\PosSupplier;
use Illuminate\Http\Request;

class PosSupplierController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['name', 'created_at'];

    public function index(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
            ? $request->input('order_by_key', 'name')
            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $suppliers = PosSupplier::query()
            ->with(['createdBy'])
            ->when($request->search, function ($query, $search) {
                // Case-insensitive search using LOWER() for PostgreSQL and MySQL compatibility
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $suppliers, [
                'success' => true,
                'message' => __('pos.suppliers.list'),
                'data' => PosSupplierResource::collection($suppliers),
            ])
        );
    }

    public function store(PosSupplierRequest $request)
    {
        $supplier = PosSupplier::create([
            'name'       => $request->name,
            'address'    => $request->address,
            'phone'      => $request->phone,
            'created_by' => $request->user()->id,
            'company_id' => $request->user()->company_id,
        ]);

        $supplier->load('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.suppliers.stored'),
            'data'    => new PosSupplierResource($supplier),
        ], 201);
    }

    public function show(PosSupplier $supplier)
    {
        $supplier->loadMissing('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.suppliers.detail'),
            'data'    => new PosSupplierResource($supplier),
        ]);
    }

    public function update(PosSupplierRequest $request, PosSupplier $supplier)
    {
        $supplier->update(array_filter([
            'name'    => $request->has('name') ? $request->name : null,
            'address' => $request->has('address') ? $request->address : null,
            'phone'   => $request->has('phone') ? $request->phone : null,
        ], fn($value) => !is_null($value)));

        $supplier->load('createdBy');

        return response()->json([
            'success' => true,
            'message' => __('pos.suppliers.updated'),
            'data'    => new PosSupplierResource($supplier),
        ]);
    }

    public function destroy(PosSupplier $supplier)
    {
        if ($supplier->purchaseTransactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('pos.suppliers.has_purchases'),
                'code'    => 422,
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => __('pos.suppliers.deleted'),
        ]);
    }
}
