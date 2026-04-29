<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarketingRequest;
use App\Http\Resources\MarketingResource;
use App\Models\Marketing;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    protected array $sortableColumns = ['name', 'phone', 'created_at'];

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'name')
                            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $marketings = Marketing::when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('marketings.list'),
            'data'    => MarketingResource::collection($marketings),
        ]);
    }

    public function store(MarketingRequest $request)
    {
        $marketing = Marketing::create([
            'name'       => $request->name,
            'address'    => $request->address,
            'phone'      => $request->phone,
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('marketings.stored'),
            'data'    => new MarketingResource($marketing),
        ], 201);
    }

    public function show(Marketing $marketing)
    {
        return response()->json([
            'success' => true,
            'message' => __('marketings.detail'),
            'data'    => new MarketingResource($marketing),
        ]);
    }

    public function update(MarketingRequest $request, Marketing $marketing)
    {
        $marketing->update($request->only(['name', 'address', 'phone']));

        return response()->json([
            'success' => true,
            'message' => __('marketings.updated'),
            'data'    => new MarketingResource($marketing),
        ]);
    }

    public function destroy(Marketing $marketing)
    {
        $hasProducts      = $marketing->marketingProducts()->exists();
        $hasTransactions  = $marketing->saleTransactions()->exists();

        if ($hasProducts || $hasTransactions) {
            return response()->json([
                'success' => false,
                'message' => __('marketings.has_relations'),
                'code'    => 422,
            ], 422);
        }

        $marketing->delete();

        return response()->json([
            'success' => true,
            'message' => __('marketings.deleted'),
        ]);
    }
}