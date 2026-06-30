<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosCustomerTypeRequest;
use App\Http\Resources\Pos\PosCustomerTypeResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\PosCustomerType;
use Illuminate\Http\Request;

class PosCustomerTypeController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['type', 'discount', 'created_at'];

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'type'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'type')
                            : 'type';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $customerTypes = PosCustomerType::query()
            ->when($request->search, function ($query, $search) {
                $query->whereRaw('LOWER(type) LIKE ?', ['%' . strtolower($search) . '%']);
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $customerTypes, [
                'success' => true,
                'message' => __('pos.customer_types.list'),
                'data'    => PosCustomerTypeResource::collection($customerTypes),
            ])
        );
    }

    public function store(PosCustomerTypeRequest $request)
    {
        $customerType = PosCustomerType::create([
            'type'       => $request->type,
            'discount'   => $request->discount ?? 0,
            'created_by' => $request->user()->id,
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('pos.customer_types.stored'),
            'data'    => new PosCustomerTypeResource($customerType),
        ], 201);
    }

    public function show(PosCustomerType $customerType)
    {
        return response()->json([
            'success' => true,
            'message' => __('pos.customer_types.detail'),
            'data'    => new PosCustomerTypeResource($customerType),
        ]);
    }

    public function update(PosCustomerTypeRequest $request, PosCustomerType $customerType)
    {
        $customerType->update($request->only(['type', 'discount']));

        return response()->json([
            'success' => true,
            'message' => __('pos.customer_types.updated'),
            'data'    => new PosCustomerTypeResource($customerType),
        ]);
    }

    public function destroy(PosCustomerType $customerType)
    {
        if ($customerType->customers()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('pos.customer_types.has_customers'),
                'code'    => 422,
            ], 422);
        }

        $customerType->delete();

        return response()->json([
            'success' => true,
            'message' => __('pos.customer_types.deleted'),
        ]);
    }
}
