<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class StockMutationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Product) {
            return $this->formatProduct($request);
        }
        
        if ($this->resource instanceof LengthAwarePaginator) {
            return $this->formatPaginatedMutations($request);
        }
        
        return $this->formatMutation($request);
    }

    private function formatProduct(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'current_stock' => $this->stock,
        ];
    }

    private function formatMutation(Request $request): array
    {
        return [
            'ulid' => (string) $this->ulid,
            'type_label' => $this->type->label(),
            'quantity' => (int) $this->quantity,
            'stock_before' => (int) $this->stock_before,
            'stock_after' => (int) $this->stock_after,
            'notes' => $this->notes,
            // 'created_by' => $this->whenLoaded('creator', function () {
            //     return [
            //         'id' => $this->creator->id,
            //         'name' => $this->creator->name,
            //     ];
            // }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function formatPaginatedMutations(Request $request): array
    {
        $collection = $this->resource->getCollection();
        
        return [
            'data' => self::collection($collection),
        ];
    }
}