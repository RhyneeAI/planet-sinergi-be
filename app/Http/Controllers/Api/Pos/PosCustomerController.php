<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosCustomerRequest;
use App\Http\Resources\Pos\PosCustomerResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\PosCustomer;
use Illuminate\Http\Request;

class PosCustomerController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['name', 'phone', 'created_at'];

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'name')
                            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $customers = PosCustomer::with(['createdBy', 'customerType'])
                ->when($request->search, function ($query, $search) {
                // Case-insensitive search using LOWER() for PostgreSQL and MySQL compatibility
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $customers, [
                'success' => true,
                'message' => __('pos.customers.list'),
                'data'    => PosCustomerResource::collection($customers),
            ])
        );
    }


    public function store(PosCustomerRequest $request)
    {
        $customerTypeId = $request->getCustomerTypeId();

        $customer = PosCustomer::create([
            'name'             => $request->name,
            'address'          => $request->address,
            'phone'            => $request->phone,
            'customer_type_id' => $customerTypeId,
            'created_by'       => $request->user()->id,
            'company_id'       => $request->user()->company_id,
        ]);

        $customer->load(['createdBy', 'customerType']);

        return response()->json([
            'success' => true,
            'message' => __('pos.customers.stored'),
            'data'    => new PosCustomerResource($customer),
        ], 201);
    }

    public function show(PosCustomer $customer)
    {
        $customer->loadMissing(['createdBy', 'customerType']);

        return response()->json([
            'success' => true,
            'message' => __('pos.customers.detail'),
            'data'    => new PosCustomerResource($customer),
        ]);
    }

    public function update(PosCustomerRequest $request, PosCustomer $customer)
    {
        $updateData = array_filter([
            'name'             => $request->has('name') ? $request->name : null,
            'address'          => $request->has('address') ? $request->address : null,
            'phone'            => $request->has('phone') ? $request->phone : null,
            'customer_type_id' => $request->has('customer_type_uuid') ? $request->getCustomerTypeId() : null,
        ], fn($value) => !is_null($value));

        $customer->update($updateData);

        $customer->load(['createdBy', 'customerType']);

        return response()->json([
            'success' => true,
            'message' => __('pos.customers.updated'),
            'data'    => new PosCustomerResource($customer),
        ]);
    }

    public function destroy(PosCustomer $customer)
    {
        if ($customer->salesTransactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('pos.customers.has_transactions'),
                'code'    => 422,
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => __('pos.customers.deleted'),
        ]);
    }
}
