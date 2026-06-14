<?php

namespace App\Http\Controllers\Api\Operational;

use App\Http\Controllers\Controller;
use App\Http\Resources\Operational\OpsNotificationResource;
use App\Models\OpsExpense;
use App\Models\OpsNotification;
use App\Models\OpsTransferConfirmation;
use App\Services\Operational\OpsNotificationService;
use Illuminate\Http\Request;

class OpsNotificationController extends Controller
{
    public function __construct(
        protected OpsNotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $notifications = OpsNotification::with(['notifiable' => function ($morphTo) {
            $morphTo->morphWith([
                OpsTransferConfirmation::class => ['confirmable'],
                OpsExpense::class => [],
            ]);
        }])
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread_only') == true, fn($q) => $q->where('is_read', false))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.notifications.list'),
            'data' => OpsNotificationResource::collection($notifications),
            'meta' => [
                'unread_count' => OpsNotification::where('user_id', $request->user()->id)
                    ->where('is_read', false)
                    ->count(),
            ],
        ]);
    }

    public function markAsRead(OpsNotification $opsNotification)
    {
        if ($opsNotification->user_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403,
            ], 403);
        }

        $notification = $this->notificationService->markAsRead($opsNotification);

        return response()->json([
            'success' => true,
            'message' => __('operational.notifications.read'),
            'data' => new OpsNotificationResource($notification),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'success' => true,
            'message' => __('operational.notifications.read_all'),
            'data' => ['updated_count' => $count],
        ]);
    }
}
