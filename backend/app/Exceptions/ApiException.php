<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Base class for expected, client-facing API errors.
 *
 * Subclasses define a stable machine-readable error code and an HTTP status.
 * They are rendered in the standard error envelope and are not reported to
 * the error log, because they describe client mistakes, not server faults.
 */
abstract class ApiException extends RuntimeException
{
    abstract public function status(): int;

    abstract public function errorCode(): string;

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status(), $this->errorCode());
    }
}
