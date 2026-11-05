<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception raised by an API request in the standard error
 * envelope. Registered in bootstrap/app.php.
 *
 * Internal details (exception class, message, location) are included only
 * when APP_DEBUG is true, and never a stack trace.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $e instanceof ApiException => $e->render(),
            $e instanceof ValidationException => ApiResponse::error(
                __('api.errors.validation'),
                $e->status,
                'validation_failed',
                $e->errors(),
            ),
            $e instanceof AuthenticationException => ApiResponse::error(
                __('api.errors.unauthenticated'),
                401,
                'unauthenticated',
            ),
            $e instanceof HttpExceptionInterface => $this->renderHttpException($e),
            default => $this->renderServerError($e),
        };
    }

    /**
     * Framework HTTP exceptions, including authorization failures and missing
     * models, which Laravel converts to 403/404 before this renderer runs.
     */
    private function renderHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        // A policy that denied with an explicit code (Response::deny($message,
        // $code) or denyAsNotFound($message, $code)) chose a client-facing
        // message and code; render exactly those.
        $previous = $e instanceof Throwable ? $e->getPrevious() : null;
        if ($previous instanceof AuthorizationException && is_string($previous->response()?->code())) {
            return ApiResponse::error(
                (string) $previous->response()->message(),
                $status,
                $previous->response()->code(),
                headers: $e->getHeaders(),
            );
        }

        [$code, $messageKey] = match ($status) {
            401 => ['unauthenticated', 'api.errors.unauthenticated'],
            403 => ['forbidden', 'api.errors.forbidden'],
            404 => ['not_found', 'api.errors.not_found'],
            405 => ['method_not_allowed', 'api.errors.method_not_allowed'],
            429 => ['too_many_requests', 'api.errors.too_many_requests'],
            503 => ['service_unavailable', 'api.errors.service_unavailable'],
            default => $status >= 500
                ? ['server_error', 'api.errors.server']
                : ['http_error', 'api.errors.http'],
        };

        return ApiResponse::error(__($messageKey), $status, $code, headers: $e->getHeaders());
    }

    private function renderServerError(Throwable $e): JsonResponse
    {
        $debug = config('app.debug') ? [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'location' => basename($e->getFile()).':'.$e->getLine(),
        ] : null;

        return ApiResponse::error(__('api.errors.server'), 500, 'server_error', debug: $debug);
    }
}
