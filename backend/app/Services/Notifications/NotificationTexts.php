<?php

namespace App\Services\Notifications;

use App\Enums\AppLocale;
use App\Enums\NotificationKind;

/**
 * Title and text of a notification in one language. Notifications store
 * facts, not sentences, so each reader gets their own language: the device
 * language for push, the app language in the notification center.
 */
final class NotificationTexts
{
    /**
     * @param  array<string, mixed>  $facts  As stored by QueueAlert.
     * @return array{title: string, body: string}
     */
    public function render(NotificationKind $kind, array $facts, string $locale): array
    {
        $locale = AppLocale::tryFrom($locale)?->value ?? AppLocale::English->value;
        $ahead = $facts['people_ahead'] ?? null;
        $replace = [
            'business' => (string) ($facts['business']['name'] ?? ''),
            'queue' => (string) ($facts['queue']['name'] ?? ''),
            'number' => (string) ($facts['ticket']['number'] ?? ''),
            'ahead' => (string) ($ahead ?? ''),
        ];
        $bodyKey = $kind === NotificationKind::TicketAlmostTurn && $ahead === 0 ? 'body_next' : 'body';

        return [
            'title' => __('notifications.'.$kind->value.'.title', $replace, $locale),
            'body' => __('notifications.'.$kind->value.'.'.$bodyKey, $replace, $locale),
        ];
    }

    /**
     * The language a request asks for (Accept-Language), else the fallback.
     */
    public static function requestLocale(?string $acceptLanguage, string $fallback): string
    {
        $primary = strtolower(substr(trim((string) $acceptLanguage), 0, 2));

        return AppLocale::tryFrom($primary)?->value ?? AppLocale::tryFrom($fallback)?->value ?? AppLocale::English->value;
    }
}
