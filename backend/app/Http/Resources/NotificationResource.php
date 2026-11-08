<?php

namespace App\Http\Resources;

use App\Enums\NotificationKind;
use App\Services\Notifications\NotificationTexts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One entry of the notification center, with its title and text in the
 * reader's language (Accept-Language, else the account language).
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->resource;
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $kind = NotificationKind::tryFrom((string) ($data['kind'] ?? $notification->type));
        $locale = NotificationTexts::requestLocale(
            $request->header('Accept-Language'),
            (string) ($request->user()?->locale ?? 'en'),
        );
        $text = $kind === null
            ? ['title' => '', 'body' => '']
            : app(NotificationTexts::class)->render($kind, $data, $locale);

        return [
            'id' => $notification->id,
            'kind' => $kind?->value ?? $notification->type,
            'title' => $text['title'],
            'body' => $text['body'],
            'ticket' => isset($data['ticket']['id']) ? [
                'id' => $data['ticket']['id'],
                'number' => $data['ticket']['number'] ?? null,
            ] : null,
            'business' => isset($data['business']['id']) ? [
                'id' => $data['business']['id'],
                'name' => $data['business']['name'] ?? null,
            ] : null,
            'queue' => isset($data['queue']['id']) ? [
                'id' => $data['queue']['id'],
                'name' => $data['queue']['name'] ?? null,
            ] : null,
            'for_staff' => $kind?->forStaff() ?? false,
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
