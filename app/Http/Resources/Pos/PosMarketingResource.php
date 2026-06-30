<?php

namespace App\Http\Resources\Pos;

use App\Enums\Role;
use App\Http\Resources\Operational\OpsMarketingLeaderSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosMarketingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->role instanceof Role ? $this->role : Role::tryFrom((string) $this->role);

        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => $role?->value,
            'leader' => $this->when(
                $role === Role::MARKETING && $this->relationLoaded('leaderUser'),
                fn () => $this->leaderUser->isNotEmpty()
                    ? new OpsMarketingLeaderSummaryResource($this->leaderUser->first())
                    : null
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
