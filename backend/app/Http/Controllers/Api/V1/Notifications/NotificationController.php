<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The signed-in user's notification center (docs/NOTIFICATION_CENTER.md).
 * Only the user's own notifications: another user's id is a 404.
 */
class NotificationController extends Controller
{
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->notifications()->latest()->orderByDesc('id');
        match ($request->filter()) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => null,
        };
        $paginator = $query->paginate((int) config('dory.notifications.per_page'));

        return ApiResponse::success(
            NotificationResource::collection($paginator->items()),
            __('api.notifications.listed'),
            meta: [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        );
    }

    public function read(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $notification->markAsRead();

        return ApiResponse::success(
            new NotificationResource($notification),
            __('api.notifications.read'),
            meta: ['unread_count' => $request->user()->unreadNotifications()->count()],
        );
    }

    public function readAll(Request $request): JsonResponse
    {
        $marked = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(
            ['marked' => $marked],
            __('api.notifications.all_read'),
            meta: ['unread_count' => 0],
        );
    }
}
