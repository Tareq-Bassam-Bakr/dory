<?php

namespace App\Http\Controllers\Api\V1\Queues;

use App\Enums\JoinSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queues\GuestJoinRequest;
use App\Http\Resources\CustomerTicketResource;
use App\Http\Resources\PublicQueueResource;
use App\Models\Queue;
use App\Models\QueueTicket;
use App\Services\Queues\TicketCancellationService;
use App\Services\Queues\TicketJoinService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Guests, through a queue's public link or QR code. No account needed.
 *
 * - {publicQueueToken}: the 40-character token in the public link.
 * - {joinableQueue}: the queue's public id, from discovery in the app. Same
 *   rules as the link: the queue must allow guests, and the ticket works
 *   exactly like one created from the link.
 * - {publicTicket}: a guest ticket; the X-Ticket-Token header returned
 *   when joining is checked by EnsureTicketAccessToken.
 */
class PublicQueueController extends Controller
{
    public function __construct(
        private readonly TicketJoinService $joins,
        private readonly TicketCancellationService $cancellations,
    ) {}

    public function show(Queue $publicQueueToken): JsonResponse
    {
        return ApiResponse::success(new PublicQueueResource($publicQueueToken->load('activeSession')));
    }

    /**
     * The response contains the ticket access token once. Send it back in
     * the X-Ticket-Token header to follow or cancel the ticket.
     */
    public function join(GuestJoinRequest $request, Queue $publicQueueToken): JsonResponse
    {
        $result = $this->joins->joinAsGuest(
            $publicQueueToken,
            $request->guestName(),
            $request->guestPhone(),
            $request->idempotencyKey(),
        );

        $resource = (new CustomerTicketResource($this->loadTicket($result->ticket)))->withAccessToken($result->accessToken);

        return $result->created
            ? ApiResponse::created($resource, __('api.queue.joined'))
            : ApiResponse::success($resource, __('api.queue.already_joined'));
    }

    /**
     * The same guest join, from the app: the customer found the queue through
     * discovery and never saw its link token.
     */
    public function joinById(GuestJoinRequest $request, Queue $joinableQueue): JsonResponse
    {
        $result = $this->joins->joinAsGuest(
            $joinableQueue,
            $request->guestName(),
            $request->guestPhone(),
            $request->idempotencyKey(),
            JoinSource::App,
        );

        $resource = (new CustomerTicketResource($this->loadTicket($result->ticket)))->withAccessToken($result->accessToken);

        return $result->created
            ? ApiResponse::created($resource, __('api.queue.joined'))
            : ApiResponse::success($resource, __('api.queue.already_joined'));
    }

    public function ticket(QueueTicket $publicTicket): JsonResponse
    {
        return ApiResponse::success(new CustomerTicketResource($publicTicket));
    }

    public function cancel(QueueTicket $publicTicket): JsonResponse
    {
        $ticket = $this->cancellations->cancelByHolder($publicTicket, null);

        return ApiResponse::success(new CustomerTicketResource($this->loadTicket($ticket)), __('api.queue.ticket_cancelled'));
    }

    private function loadTicket(QueueTicket $ticket): QueueTicket
    {
        return $ticket->loadMissing(['queue.branch.business', 'queue.service', 'session']);
    }
}
