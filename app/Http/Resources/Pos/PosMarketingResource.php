<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosMarketingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'address'    => $this->address,
            'phone'      => $this->phone,
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'name' => $this->createdBy->name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
