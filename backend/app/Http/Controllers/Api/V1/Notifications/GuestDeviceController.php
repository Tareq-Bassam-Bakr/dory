<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Exceptions\NotificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\RegisterDeviceRequest;
use App\Http\Requests\Notifications\UnregisterGuestDeviceRequest;
use App\Http\Resources\PushDeviceResource;
use App\Models\PushDevice;
use App\Models\QueueTicket;
use App\Services\Push\PushDeviceRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Push devices of a guest ticket (docs/DEVICE_TOKEN_SECURITY.md). The
 * ticket.token middleware has already checked the ticket's access token
 * (X-Ticket-Token header, never the URL): without it, the ticket does not
 * exist for this request.
 */
class GuestDeviceController extends Controller
{
    public function __construct(private readonly PushDeviceRegistry $registry) {}

    public function register(RegisterDeviceRequest $request, QueueTicket $publicTicket): JsonResponse
    {
        $device = $this->registry->registerForGuestTicket($publicTicket, $request->device());

        return ApiResponse::created(new PushDeviceResource($device), __('api.notifications.device_registered'));
    }

    public function unregister(UnregisterGuestDeviceRequest $request, QueueTicket $publicTicket): JsonResponse
    {
        $device = PushDevice::query()
            ->where('public_id', $request->validated('device'))
            ->where('queue_ticket_id', $publicTicket->getKey())
            ->first() ?? throw NotificationException::deviceNotFound();

        $this->registry->revoke($device);

        return ApiResponse::success(null, __('api.notifications.device_unregistered'));
    }
}
