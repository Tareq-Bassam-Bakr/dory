<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Builds every JSON response of the API, so all endpoints share one envelope.
 *
 * Success: { success: true,  message, data, meta }
 * Error:   { success: false, message, code, errors }
 *
 * See docs/API_CONVENTIONS.md.
 */
final class ApiResponse
{
    /**
     * Unicode stays unescaped so Arabic text is readable and compact; the
     * default HTML-safe escaping of <, >, ', " and & is kept.
     */
    private const ENCODING_OPTIONS = JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_UNESCAPED_UNICODE;

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, string>  $headers
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        ?array $meta = null,
        array $headers = [],
    ): JsonResponse {
        return self::envelope(true, $message ?? __('api.success'), $data, $status, $meta, $headers);
    }

    /**
     * Data envelope with an explicit success flag. Used directly only where a
     * failure must still carry data, e.g. the health check's per-check results.
     *
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, string>  $headers
     */
    public static function envelope(
        bool $success,
        string $message,
        mixed $data,
        int $status,
        ?array $meta = null,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $status, $headers, self::ENCODING_OPTIONS);
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * @param  array<string, list<string>>|null  $errors  Field errors (validation only).
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $debug  Only ever passed when app.debug is on.
     */
    public static function error(
        string $message,
        int $status,
        string $code,
        ?array $errors = null,
        array $headers = [],
        ?array $debug = null,
        ?array $details = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
        ];

        // Machine-readable context, e.g. a plan limit and its numbers.
        if ($details !== null) {
            $payload['details'] = $details;
        }

        if ($debug !== null) {
            $payload['debug'] = $debug;
        }

        return new JsonResponse($payload, $status, $headers, self::ENCODING_OPTIONS);
    }

    /**
     * Standard paginated list: items in `data`, paging details in `meta.pagination`.
     *
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        ?string $message = null,
    ): JsonResponse {
        return self::success(
            $resourceClass::collection($paginator->items()),
            $message,
            meta: [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        );
    }
}
