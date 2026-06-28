<?php

namespace App\Http\Resources\Absence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsOvertimeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn() => [
                'uuid' => (string) $this->user->uuid,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
            ]),
            'date' => $this->date->toDateString(),
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'reason' => $this->reason,
            'status' => $this->status->value,
            'approved_by' => $this->whenLoaded('approver', fn() => $this->approver?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
