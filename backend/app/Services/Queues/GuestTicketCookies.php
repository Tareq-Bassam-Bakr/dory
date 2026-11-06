<?php

namespace App\Services\Queues;

use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Guest tickets on the web page (docs/GUEST_JOINING.md).
 *
 * The ticket access token lives in one cookie per ticket,
 * "dory_ticket_{ticket public id}": encrypted with APP_KEY (EncryptCookies),
 * HttpOnly (no JavaScript access), SameSite=Strict (never sent by other
 * sites, which also protects the cancel form against CSRF) and Secure over
 * HTTPS. The token never appears in a URL, a page or a log.
 */
final class GuestTicketCookies
{
    public const PREFIX = 'dory_ticket_';

    public const FORM_COOKIE = 'dory_form';

    public const MAX_TICKETS = 10;

    public static function name(string $ticketPublicId): string
    {
        return self::PREFIX.$ticketPublicId;
    }

    public static function forTicket(Request $request, QueueTicket $ticket, string $token): Cookie
    {
        return self::make($request, self::name($ticket->public_id), $token, (int) config('dory.queues.guest_cookie_days') * 1440);
    }

    /**
     * Double-submit nonce for the join form (the API has no sessions).
     */
    public static function forForm(Request $request, string $nonce): Cookie
    {
        return self::make($request, self::FORM_COOKIE, $nonce, 60);
    }

    /**
     * The ticket, when this browser holds its access token.
     */
    public static function ticket(Request $request, string $publicId): ?QueueTicket
    {
        $token = $request->cookie(self::name($publicId));
        if (! is_string($token)) {
            return null;
        }

        $ticket = QueueTicket::query()
            ->where('public_id', $publicId)
            ->whereNotNull('public_access_token_hash')
            ->with(['queue.branch.business', 'queue.service', 'session'])
            ->first();

        return $ticket?->accessTokenMatches($token) ? $ticket : null;
    }

    /**
     * Active tickets of the queue held by this browser.
     *
     * @return Collection<int, QueueTicket>
     */
    public static function activeTickets(Request $request, Queue $queue): Collection
    {
        $held = collect($request->cookies->all())
            ->filter(fn ($value, $name) => is_string($value) && str_starts_with((string) $name, self::PREFIX))
            ->mapWithKeys(fn ($value, $name) => [substr((string) $name, strlen(self::PREFIX)) => $value])
            ->filter(fn ($value, $id) => preg_match('/^[0-9A-Za-z]{26}$/', (string) $id) === 1)
            ->take(self::MAX_TICKETS);

        if ($held->isEmpty()) {
            return collect();
        }

        return QueueTicket::query()
            ->whereIn('public_id', $held->keys()->all())
            ->where('queue_id', $queue->getKey())
            ->whereIn('status', TicketStatus::activeValues())
            ->whereNotNull('public_access_token_hash')
            ->get()
            ->filter(fn (QueueTicket $ticket) => $ticket->accessTokenMatches($held[$ticket->public_id]))
            ->values();
    }

    private static function make(Request $request, string $name, string $value, int $minutes): Cookie
    {
        return cookie(
            $name,
            $value,
            $minutes,
            path: '/',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }
}
