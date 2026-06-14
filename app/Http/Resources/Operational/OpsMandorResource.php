<?php

namespace App\Http\Resources\Operational;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsMandorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
