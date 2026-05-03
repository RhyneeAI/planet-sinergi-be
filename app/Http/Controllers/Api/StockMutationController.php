<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMutationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockMutationRequest;
use App\Http\Resources\StockMutationResource;
use App\Models\Product;
use App\Models\StockMutation;
use Illuminate\Http\Request;

class StockMutationController extends Controller
{
    public function index(Request $request)
    {
        $sortableColumns = ['product_name', 'created_at'];
        
        $orderByKey = in_array($request->input('order_by_key', 'product_name'), $sortableColumns)
            ? $request->input('order_by_key', 'product_name')
            : 'product_name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        // Ambil product_id unik dari stock_mutations
        $query = StockMutation::query()
            ->when($request->date_from, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            });

        $productIds = $query->distinct()->pluck('product_id');

        $products = Product::whereIn('id', $productIds);

        // Sorting by product name
        if ($orderByKey === 'product_name') {
            $products->orderBy('name', $orderByValue);
        }
        // Sorting by created_at (ambil created_at terbaru dari mutasi)
        elseif ($orderByKey === 'created_at') {
            $products->orderBy(
                StockMutation::select('created_at')
                    ->whereColumn('product_id', 'products.id')
                    ->orderBy('created_at', 'desc')
                    ->limit(1),
                $orderByValue
            );
        }

        $products = $products->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('stock_mutations.product_list'),
            'data' => StockMutationResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function store(StockMutationRequest $request)
    {
        // Cari product berdasarkan UUID
        $product = Product::where('company_id', $request->user()->company_id)
            ->where('uuid', $request->product_uuid) 
            ->firstOrFail();

        $stockBefore = $product->stock;
        
        $allowedTypes = [StockMutationType::ADJUST_IN, StockMutationType::ADJUST_OUT, StockMutationType::OPNAME];
        
        $type = StockMutationType::tryFrom($request->type);
        
        if (!$type || !in_array($type, $allowedTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Manual creation only allowed for ADJUST_IN, ADJUST_OUT, and OPNAME.',
                'code' => 422,
            ], 422);
        }
        
        $stockAfter = match ($type) {
            StockMutationType::ADJUST_IN => $stockBefore + $request->quantity,
            StockMutationType::ADJUST_OUT => $stockBefore - $request->quantity,
            StockMutationType::OPNAME => $request->quantity,
            default => $stockBefore,
        };

        if ($stockAfter < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Stock cannot be negative.',
                'code' => 422,
            ], 422);
        }

        $product->update(['stock' => $stockAfter]);

        $stockMutation = StockMutation::create([
            'type' => $type,
            'quantity' => $request->quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'notes' => $request->notes,
            'product_id' => $product->id, 
            'company_id' => $request->user()->company_id,
            'reference_id' => null,
            'created_by' => $request->user()->id,
        ]);

        $stockMutation->load(['product', 'creator']);

        return response()->json([
            'success' => true,
            'message' => __('stock_mutations.stored'),
            'data' => new StockMutationResource($stockMutation),
        ], 201);
    }

    public function show(Request $request, Product $product)
    {
        $type = $request->input('type'); 
        
        $orderByKey = in_array($request->input('order_by_key', 'created_at'), ['created_at', 'type', 'quantity'])
            ? $request->input('order_by_key', 'created_at')
            : 'created_at';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'DESC' ? 'DESC' : 'ASC';

        $stockMutations = StockMutation::with(['creator'])
            ->where('product_id', $product->id)
            ->when($type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('stock_mutations.list'),
            'data' => [
                'product' => new StockMutationResource($product), 
                'mutations' => new StockMutationResource($stockMutations), 
            ],
        ]);
    }
}