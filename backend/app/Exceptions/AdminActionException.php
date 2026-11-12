<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * A platform admin action that the current state does not allow
 * (docs/ADMIN_DASHBOARD.md): suspending your own account, approving a
 * payment twice, ... The dashboard shows the reason; the API answers 409.
 */
final class AdminActionException extends ApiException
{
    private function __construct(private readonly string $reason, private readonly int $httpStatus)
    {
        parent::__construct(__('api.admin.not_allowed.'.$reason));
    }

    public static function notAllowed(string $reason): self
    {
        return new self($reason, 409);
    }

    public static function forbidden(string $reason): self
    {
        return new self($reason, 403);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->httpStatus === 403 ? 'forbidden' : 'admin_action_not_allowed';
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->httpStatus, $this->errorCode(), details: ['reason' => $this->reason]);
    }
}
