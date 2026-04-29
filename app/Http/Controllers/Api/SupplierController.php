<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('supplier.list'),
            'data'    => SupplierResource::collection($suppliers),
        ]);
    }

    public function store(SupplierRequest $request)
    {
        $supplier = Supplier::create([
            'name'       => $request->name,
            'address'    => $request->address,
            'phone'      => $request->phone,
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('supplier.stored'),
            'data'    => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json([
            'success' => true,
            'message' => __('supplier.detail'),
            'data'    => new SupplierResource($supplier),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->name;
        }
        if ($request->has('address')) {
            $data['address'] = $request->address;
        }
        if ($request->has('phone')) {
            $data['phone'] = $request->phone;
        }

        $supplier->update($data);

        return response()->json([
            'success' => true,
            'message' => __('supplier.updated'),
            'data'    => new SupplierResource($supplier),
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseTransactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('supplier.has_purchases'),
                'code'    => 422,
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => __('supplier.deleted'),
        ]);
    }
}