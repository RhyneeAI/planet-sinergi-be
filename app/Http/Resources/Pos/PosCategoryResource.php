<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // 'id'         => $this->id,
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'name' => $this->createdBy->name,
                ];
            }),
            // 'company_id' => $this->company_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
