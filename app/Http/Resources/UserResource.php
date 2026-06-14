<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role?->value,
            'address'    => $this->address,
            'phone'      => $this->phone,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}