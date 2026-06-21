<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\PosStockMutationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StockMutationRequest;
use App\Http\Resources\Pos\StockMutationResource;
use App\Models\PosProduct;
use App\Models\PosStockMutation;
use App\Services\Pos\PosStockMutationService;
use Illuminate\Http\Request;

class StockMutationController extends Controller
{
    public function __construct(
        protected PosStockMutationService $stockMutationService,
    ) {}

    public function index(Request $request)
    {
        $sortableColumns = ['product_name', 'created_at'];

        $orderByKey = in_array($request->input('order_by_key', 'product_name'), $sortableColumns)
            ? $request->input('order_by_key', 'product_name')
            : 'product_name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $query = PosStockMutation::query()
            ->when($request->date_from, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('product', function ($productQuery) use ($search) {
                        $productQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
                    });
                });
            });

        $productIds = $query->distinct()->pluck('product_id');

        $products = PosProduct::with(['category', 'unit'])
            ->whereIn('id', $productIds);

        if ($orderByKey === 'product_name') {
            $products->orderBy('name', $orderByValue);
        } elseif ($orderByKey === 'created_at') {
            $products->orderBy(
                PosStockMutation::select('created_at')
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
        $product = PosProduct::where('company_id', $request->user()->company_id)
            ->where('uuid', $request->product_uuid)
            ->firstOrFail();

        $stockBefore = (int) $product->stock;

        $allowedTypes = [PosStockMutationType::ADJUST_IN, PosStockMutationType::ADJUST_OUT, PosStockMutationType::OPNAME];

        $type = PosStockMutationType::tryFrom($request->type);

        if (!$type || !in_array($type, $allowedTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Manual creation only allowed for ADJUST_IN, ADJUST_OUT, and OPNAME.',
                'code' => 422,
            ], 422);
        }

        $stockAfter = match ($type) {
            PosStockMutationType::ADJUST_IN => $stockBefore + (int) $request->quantity,
            PosStockMutationType::ADJUST_OUT => $stockBefore - (int) $request->quantity,
            PosStockMutationType::OPNAME => (int) $request->quantity,
            default => $stockBefore,
        };

        if ($stockAfter < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Stock cannot be negative.',
                'code' => 422,
            ], 422);
        }

        $this->stockMutationService->adjustStock($product, $stockAfter);

        $stockMutation = $this->stockMutationService->create(
            product: $product,
            type: $type,
            quantity: (int) $request->quantity,
            stockBefore: $stockBefore,
            stockAfter: $stockAfter,
            notes: $request->notes,
            companyId: $request->user()->company_id,
            reference: null,
            createdBy: $request->user()->id,
        );

        $stockMutation->load(['product', 'creator']);

        return response()->json([
            'success' => true,
            'message' => __('stock_mutations.stored'),
            'data' => new StockMutationResource($stockMutation),
        ], 201);
    }

    public function show(Request $request, PosProduct $product)
    {
        $type = $request->input('type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $orderByKey = in_array($request->input('order_by_key', 'created_at'), ['created_at', 'type', 'quantity'])
            ? $request->input('order_by_key', 'created_at')
            : 'created_at';
        $orderByValue = strtoupper($request->input('order_by_value', 'DESC')) === 'DESC' ? 'DESC' : 'ASC';

        $stockMutations = PosStockMutation::with(['creator'])
            ->where('product_id', $product->id)
            ->when($type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($search, function ($query, $search) {
                $searchLower = strtolower($search);
                $query->where(function ($q) use ($searchLower) {
                    $q->whereRaw('LOWER(type) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(notes) LIKE ?', ["%{$searchLower}%"]);
                });
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
