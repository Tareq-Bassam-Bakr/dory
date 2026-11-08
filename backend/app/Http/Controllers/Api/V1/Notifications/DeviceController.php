<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\RegisterDeviceRequest;
use App\Http\Resources\PushDeviceResource;
use App\Models\PushDevice;
use App\Services\Push\PushDeviceRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Push devices of the signed-in user (docs/DEVICE_TOKEN_SECURITY.md). A
 * device always belongs to the authenticated user: there is no way to name
 * another account.
 */
class DeviceController extends Controller
{
    public function __construct(private readonly PushDeviceRegistry $registry) {}

    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->registry->registerForUser($request->user(), $request->device());

        return ApiResponse::created(new PushDeviceResource($device), __('api.notifications.device_registered'));
    }

    /**
     * {device} is bound to the user's own devices; anything else is a 404.
     */
    public function unregister(PushDevice $device): JsonResponse
    {
        $this->registry->revoke($device);

        return ApiResponse::success(null, __('api.notifications.device_unregistered'));
    }
}
