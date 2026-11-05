<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation id.
 *
 * A well-formed incoming X-Request-Id (e.g. from a gateway) is kept;
 * otherwise a UUID is generated. The id is added to every log entry through
 * the Context facade and returned in the X-Request-Id response header.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->acceptOrGenerate($request->headers->get(self::HEADER));

        $request->headers->set(self::HEADER, $requestId);
        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    /**
     * Only short, log-safe ids are accepted, so a client cannot inject
     * arbitrary content into logs.
     */
    private function acceptOrGenerate(?string $incoming): string
    {
        if ($incoming !== null && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::uuid();
    }
}
