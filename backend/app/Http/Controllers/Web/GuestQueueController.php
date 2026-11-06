<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\QueueException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queues\GuestJoinRequest;
use App\Http\Resources\PublicQueueResource;
use App\Models\Queue;
use App\Models\QueueTicket;
use App\Services\Queues\GuestTicketCookies;
use App\Services\Queues\PublicQueueResolver;
use App\Services\Queues\TicketCancellationService;
use App\Services\Queues\TicketJoinService;
use App\Services\Queues\TicketPositions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The page behind each queue's QR code: a guest sees the queue, joins with
 * the minimum details and follows the ticket, in English or Arabic, with no
 * app or account. The page refreshes itself (no real-time push yet).
 *
 * GET  /join/{token}            queue and join form
 * POST /join/{token}            join, then redirect to the ticket page
 * GET  /ticket/{id}             the ticket (needs this browser's cookie)
 * POST /ticket/{id}/cancel      leave the queue
 */
class GuestQueueController extends Controller
{
    private const REFRESH_SECONDS = 20;

    public function __construct(
        private readonly PublicQueueResolver $resolver,
        private readonly TicketJoinService $joins,
        private readonly TicketCancellationService $cancellations,
        private readonly TicketPositions $positions,
    ) {}

    public function show(Request $request, string $guestToken): Response|RedirectResponse
    {
        $queue = $this->queue($guestToken);
        if ($queue === null) {
            return $this->message(404, 'link_not_found');
        }

        return $this->joinPage($request, $queue, $guestToken);
    }

    public function join(Request $request, string $guestToken): Response|RedirectResponse
    {
        $queue = $this->queue($guestToken);
        if ($queue === null) {
            return $this->message(404, 'link_not_found');
        }

        $nonce = $request->cookie(GuestTicketCookies::FORM_COOKIE);
        if (! is_string($nonce) || ! is_string($request->input('_form')) || ! hash_equals($nonce, $request->input('_form'))) {
            return $this->message(419, 'expired', $request->url());
        }

        // This browser already holds a ticket in the queue: show it instead.
        $held = GuestTicketCookies::activeTickets($request, $queue)->first();
        if ($held !== null) {
            return redirect()->route('guest.ticket', $held->public_id, 303);
        }

        $input = GuestJoinRequest::normalize($request->only(['name', 'phone'])) + $request->only('consent');
        $validator = Validator::make($input, GuestJoinRequest::rulesFor($queue, filled($input['phone'] ?? null)));
        if ($validator->fails()) {
            $errors = collect($validator->errors()->keys())
                ->mapWithKeys(fn (string $field) => [$field => __('guest.field_errors.'.$field)])
                ->all();

            return $this->joinPage($request, $queue, $guestToken, $errors, $input, 422);
        }

        $key = $request->input('_key');
        try {
            $result = $this->joins->joinAsGuest(
                $queue,
                $input['name'] ?? null,
                $input['phone'] ?? null,
                is_string($key) && preg_match('/^[A-Za-z0-9_-]{16,64}$/', $key) === 1 ? $key : null,
            );
        } catch (QueueException $e) {
            return $this->joinPage($request, $queue, $guestToken, ['form' => $this->errorText($e)], $input, $e->status());
        }

        return redirect()
            ->route('guest.ticket', $result->ticket->public_id, 303)
            ->withCookie(GuestTicketCookies::forTicket($request, $result->ticket, (string) $result->accessToken));
    }

    public function ticket(Request $request, string $guestTicket): Response
    {
        $ticket = GuestTicketCookies::ticket($request, $guestTicket);
        if ($ticket === null) {
            return $this->message(404, 'ticket_not_found');
        }

        return $this->ticketPage($ticket);
    }

    public function cancel(Request $request, string $guestTicket): Response|RedirectResponse
    {
        $ticket = GuestTicketCookies::ticket($request, $guestTicket);
        if ($ticket === null) {
            return $this->message(404, 'ticket_not_found');
        }

        try {
            $this->cancellations->cancelByHolder($ticket, null);
        } catch (QueueException $e) {
            return $this->ticketPage($ticket->refresh(), $this->errorText($e), $e->status());
        }

        return redirect()->route('guest.ticket', $ticket->public_id, 303);
    }

    private function queue(string $token): ?Queue
    {
        try {
            return $this->resolver->byPublicToken($token)->load('activeSession');
        } catch (QueueException) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $errors
     * @param  array<string, mixed>  $old
     */
    private function joinPage(Request $request, Queue $queue, string $token, array $errors = [], array $old = [], int $status = 200): Response
    {
        $nonce = Str::random(40);
        $info = (new PublicQueueResource($queue))->resolve($request);

        return $this->page('guest.join', [
            'queue' => $info,
            'token' => $token,
            'held' => GuestTicketCookies::activeTickets($request, $queue)->first(),
            'formNonce' => $nonce,
            'idempotencyKey' => Str::random(32),
            'fieldErrors' => $errors,
            'old' => $old,
            'refresh' => $info['accepting_joins'] ? null : self::REFRESH_SECONDS * 3,
        ], $status)->withCookie(GuestTicketCookies::forForm($request, $nonce));
    }

    private function ticketPage(QueueTicket $ticket, ?string $error = null, int $status = 200): Response
    {
        $ticket->loadMissing(['queue.branch.business', 'queue.service', 'session']);
        $queue = $ticket->queue;
        $active = $ticket->status->isActive();

        return $this->page('guest.ticket', [
            'ticket' => $ticket,
            'queue' => $queue,
            'position' => $this->positions->describe($ticket, $queue),
            'nowServing' => $active ? $this->positions->nowServing($ticket->session)->all() : [],
            'arriveBy' => $ticket->called_at?->copy()->addSeconds($queue->grace_period_seconds),
            'error' => $error,
            'refresh' => $active ? self::REFRESH_SECONDS : null,
        ], $status);
    }

    private function message(int $status, string $key, ?string $retryUrl = null): Response
    {
        return $this->page('guest.message', [
            'title' => __('guest.'.$key.'_title'),
            'body' => __('guest.'.$key.'_body'),
            'retryUrl' => $retryUrl,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, array $data, int $status): Response
    {
        return response()
            ->view($view, $data, $status)
            ->header('Cache-Control', 'no-store, private');
    }

    private function errorText(QueueException $e): string
    {
        $key = 'guest.errors.'.$e->errorCode();
        $text = __($key);

        return $text === $key ? __('guest.errors.generic') : $text;
    }
}
