<?php

namespace App\Http\Middleware;

use App\Exceptions\QueueException;
use App\Models\QueueTicket;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest ticket access: the X-Ticket-Token header must match the ticket's
 * stored hash (constant-time comparison). The token never travels in the
 * URL, so it does not end up in access logs, browser history or referrers.
 * A wrong or missing token is the same 404 as an unknown ticket.
 */
class EnsureTicketAccessToken
{
    public const HEADER = 'X-Ticket-Token';

    public function handle(Request $request, Closure $next): Response
    {
        $ticket = $request->route('publicTicket');

        if (! $ticket instanceof QueueTicket || ! $ticket->accessTokenMatches($request->header(self::HEADER))) {
            throw QueueException::notFound('ticket');
        }

        return $next($request);
    }
}
