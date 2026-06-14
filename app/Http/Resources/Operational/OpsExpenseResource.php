<?php

namespace App\Http\Resources\Operational;

use App\Services\Operational\OpsFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fileService = app(OpsFileService::class);

        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'date' => $this->date?->toDateString(),
            'proof_file' => $fileService->url($this->proof_file),
            'note' => $this->note,
            'expense_type' => $this->expense_type?->value,
            'mandor' => $this->whenLoaded('mandor', fn() => [
                'uuid' => $this->mandor->uuid,
                'name' => $this->mandor->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'uuid' => $this->createdBy->uuid,
                'name' => $this->createdBy->name,
            ]),
            'edit_logs' => $this->whenLoaded('editLogs', fn() => OpsEditLogResource::collection($this->editLogs)),
            'edit_count' => $this->when(isset($this->edit_logs_count), fn() => (int) $this->edit_logs_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
