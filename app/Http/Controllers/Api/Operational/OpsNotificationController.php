<?php

namespace App\Http\Controllers\Api\Operational;

use App\Http\Controllers\Controller;
use App\Http\Resources\Operational\OpsNotificationResource;
use App\Models\OpsNotification;
use App\Http\Traits\DataTablesResponse;
use App\Services\Operational\OpsNotificationService;
use Illuminate\Http\Request;

class OpsNotificationController extends Controller
{
    use DataTablesResponse;

    public function __construct(
        protected OpsNotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = OpsNotification::query()
            ->where('user_id', $user->id)
            ->when($request->boolean('unread_only'), fn ($query) => $query->where('is_read', false))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        $this->notificationService->enrichListActionTargets($notifications);

        return response()->json(
            $this->dataTablesResponse($request, $notifications, [
                'success' => true,
                'message' => __('operational.notifications.list'),
                'data' => OpsNotificationResource::collection($notifications),
                'meta' => [
                    'unread_count' => OpsNotification::query()
                        ->where('user_id', $user->id)
                        ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                        ->where('is_read', false)
                        ->count(),
                ],
            ])
        );
    }

    public function markAsRead(Request $request, string $uuid)
    {
        $notification = $this->notificationService->resolveForUser($request->user(), $uuid);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => __('operational.notifications.not_found'),
                'code' => 404,
            ], 404);
        }

        $notification = $this->notificationService->markAsRead($notification);

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
