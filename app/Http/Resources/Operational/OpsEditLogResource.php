<?php

namespace App\Http\Resources\Operational;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsEditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loggable_type' => $this->loggable_type,
            'loggable_id' => $this->loggable_id,
            'reason' => $this->reason,
            'old_data' => $this->old_data,
            'new_data' => $this->new_data,
            'edited_by' => $this->whenLoaded('editedBy', fn () => [
                'uuid' => $this->editedBy->uuid,
                'name' => $this->editedBy->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
