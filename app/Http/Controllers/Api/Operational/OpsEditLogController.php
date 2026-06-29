<?php

namespace App\Http\Controllers\Api\Operational;

use App\Http\Controllers\Controller;
use App\Http\Resources\Operational\OpsEditLogResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\OpsEditLog;
use Illuminate\Http\Request;

class OpsEditLogController extends Controller
{
    use DataTablesResponse;

    public function index(Request $request)
    {
        $logs = OpsEditLog::with('editedBy')
            ->when($request->loggable_type, fn ($q, $type) => $q->where('loggable_type', $type))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $logs, [
                'success' => true,
                'message' => __('operational.edit_logs.list'),
                'data' => OpsEditLogResource::collection($logs),
            ])
        );
    }
}
