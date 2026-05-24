<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // 'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'base_price' => (float) $this->base_price,
            'sales_price' => (float) $this->sales_price,
            'marketing_price' => (float) $this->marketing_price,
            // 'last_purchase_price' => (float) $this->last_purchase_price,
            'stock' => (int) $this->stock,
            'min_stock' => (int) $this->min_stock,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'name' => $this->createdBy->name,
                ];
            }),
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    // 'uuid' => $this->category->uuid,
                    'name' => $this->category->name,
                ];
            }),
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    // 'uuid' => $this->unit->uuid,
                    'name' => $this->unit->name,
                ];
            }),
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    // 'uuid' => $this->supplier->uuid,
                    'name' => $this->supplier->name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}