<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'name'             => $this->name,
            'address'          => $this->address,
            'phone'            => $this->phone,
            'customer_type_id' => $this->whenLoaded('customerType', function() {
                return [
                    'uuid'     => $this->customerType->uuid,
                    'type'     => $this->customerType->type,
                    'discount' => $this->customerType->discount,
                ];
            }),
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'name' => $this->createdBy->name,
                ];
            }),
            // 'company_id'       => $this->company_id,
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
