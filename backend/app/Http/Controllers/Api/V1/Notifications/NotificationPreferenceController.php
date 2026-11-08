<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the signed-in user wants to be told (docs/NOTIFICATION_CENTER.md).
 */
class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            NotificationPreference::for($request->user())->flags(),
            __('api.notifications.preferences'),
        );
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $preferences = NotificationPreference::for($request->user());
        $preferences->fill($request->flags())->saveOrFail();

        return ApiResponse::success($preferences->flags(), __('api.notifications.preferences_updated'));
    }
}
