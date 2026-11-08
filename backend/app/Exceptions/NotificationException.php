<?php

namespace App\Exceptions;

/**
 * Expected errors of notifications and push devices, each with a stable code
 * (docs/PUSH_NOTIFICATIONS.md).
 */
final class NotificationException extends ApiException
{
    private function __construct(private readonly int $httpStatus, private readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function deviceNotFound(): self
    {
        return new self(404, 'device_not_found', __('api.notifications.device_not_found'));
    }

    public static function notificationNotFound(): self
    {
        return new self(404, 'notification_not_found', __('api.notifications.notification_not_found'));
    }

    public static function ticketFinished(): self
    {
        return new self(422, 'ticket_finished', __('api.notifications.ticket_finished'));
    }
}
