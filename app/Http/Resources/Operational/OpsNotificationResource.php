<?php

namespace App\Http\Resources\Operational;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'type' => $this->type?->value,
            'title' => $this->title,
            'message' => $this->message,
            'is_read' => (bool) $this->is_read,
            'read_at' => $this->read_at?->toISOString(),
            'action' => [
                'notifiable_type' => $this->notifiable_type,
                'notifiable_id' => $this->notifiable_id,
                'transfer_confirmation' => $this->when(
                    $this->notifiable_type === 'ops_transfer_confirmations'
                        && $this->relationLoaded('notifiable')
                        && $this->notifiable,
                    fn() => $this->when('notifiable', fn() => OpsTransferConfirmationResource::make($this->notifiable))
                ),
                'expense' => $this->when(
                    $this->notifiable_type === 'ops_expenses'
                        && $this->relationLoaded('notifiable')
                        && $this->notifiable,
                    fn() => $this->notifiable
                ),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
