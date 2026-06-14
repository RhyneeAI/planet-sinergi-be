<?php

namespace App\Http\Resources\Operational;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'balance' => (float) $this->balance,
            'mandor' => $this->whenLoaded('mandor', fn () => [
                'uuid' => $this->mandor->uuid,
                'name' => $this->mandor->name,
            ]),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
