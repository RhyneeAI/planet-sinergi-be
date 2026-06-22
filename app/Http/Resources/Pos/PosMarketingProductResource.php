<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosMarketingProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'            => $this->uuid,
            'marketing_price' => $this->marketing_price,
            'product'         => [
                'uuid'        => $this->product->uuid,
                'name'        => $this->product->name,
                'code'        => $this->product->code,
                'sales_price' => $this->product->sales_price,
                'stock'       => $this->product->stock,
            ],
            'marketing'       => $this->whenLoaded('marketing', fn() => [
                'uuid' => $this->marketing->uuid,
                'name' => $this->marketing->name,
            ]),
            'created_by'      => $this->whenLoaded('createdBy', fn() => [
                'name' => $this->createdBy->name,
            ]),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
