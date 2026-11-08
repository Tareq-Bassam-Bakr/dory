<?php

namespace App\Services\Queues;

use App\Events\Realtime\StaffQueueUpdated;
use App\Exceptions\QueueException;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Queue configurations: create, update, delete, public join links.
 * Authorization happens before these methods are called (QueuePolicy).
 */
final class QueueService
{
    public const PUBLIC_TOKEN_LENGTH = 40;

    /**
     * @param  array<string, mixed>  $attributes  Validated configuration.
     */
    public function create(Branch $branch, Service $service, array $attributes): Queue
    {
        try {
            return DB::transaction(function () use ($branch, $service, $attributes): Queue {
                $queue = new Queue($attributes);
                $queue->branch()->associate($branch);
                $queue->service()->associate($service);
                $this->assignPublicToken($queue);
                $queue->saveOrFail();

                return $queue;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request took the prefix or the service between
            // validation and insert; the unique indexes kept the data valid.
            throw $this->conflict($branch, $service, $attributes['ticket_prefix'] ?? null);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated configuration.
     */
    public function update(Queue $queue, array $attributes): Queue
    {
        try {
            $queue->fill($attributes)->saveOrFail();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['ticket_prefix' => __('api.queue.prefix_taken')]);
        }

        $this->announceSettings($queue);

        return $queue;
    }

    /**
     * Staff dashboards learn about a changed queue at once
     * (docs/BROADCAST_CHANNELS.md): capacity, status or other settings.
     */
    private function announceSettings(Queue $queue): void
    {
        $changes = $queue->getChanges();
        unset($changes['updated_at']);
        if ($changes === []) {
            return;
        }

        $kind = match (true) {
            array_key_exists('capacity', $changes) => 'capacity_changed',
            array_key_exists('status', $changes) => 'queue_status_changed',
            default => 'settings_changed',
        };
        $queue->loadMissing(['branch.business', 'activeSession']);

        broadcast(new StaffQueueUpdated(
            $kind,
            $queue->branch->business->public_id,
            $queue->public_id,
            $queue->activeSession?->public_id,
            null,
            $queue->status->value,
            now()->toIso8601String(),
        ));
    }

    private function conflict(Branch $branch, Service $service, mixed $prefix): ValidationException
    {
        $messages = [];
        if (Queue::query()->where('active_prefix', $branch->getKey().':'.$prefix)->exists()) {
            $messages['ticket_prefix'] = __('api.queue.prefix_taken');
        }
        if (Queue::query()->where('active_slot', $service->getKey().':1')->exists()) {
            $messages['service'] = __('api.queue.service_has_queue');
        }

        return ValidationException::withMessages($messages ?: ['ticket_prefix' => __('api.queue.prefix_taken')]);
    }

    /**
     * Soft-deletes the queue (sessions and tickets stay for history) and
     * frees its prefix and service slot. Refused while a session is active.
     */
    public function delete(Queue $queue): void
    {
        DB::transaction(function () use ($queue): void {
            $locked = Queue::query()->whereKey($queue->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->activeSession()->exists()) {
                throw QueueException::queueHasActiveSession();
            }

            // Saving with deleted_at set clears the uniqueness keys (Queue::booted).
            $locked->deleted_at = now();
            $locked->public_token_hash = null;
            $locked->public_token_encrypted = null;
            $locked->saveOrFail();
        });
    }

    /**
     * Replaces the public link: the previous link stops working at once.
     */
    public function rotatePublicToken(Queue $queue): Queue
    {
        $this->assignPublicToken($queue);
        $queue->saveOrFail();

        return $queue;
    }

    /**
     * The public join URL; the QR code encodes exactly this URL.
     */
    public function publicLink(Queue $queue): ?string
    {
        $token = $queue->public_token_encrypted;

        return $token === null ? null : self::urlFor($token);
    }

    public static function urlFor(string $token): string
    {
        return str_replace('{token}', $token, (string) config('dory.queues.public_join_url'));
    }

    private function assignPublicToken(Queue $queue): void
    {
        // 40 characters from a CSPRNG; stored as a lookup hash plus an
        // encrypted copy (APP_KEY) so the link can be shown again.
        $token = Str::random(self::PUBLIC_TOKEN_LENGTH);
        $queue->public_token_hash = hash('sha256', $token);
        $queue->public_token_encrypted = $token;
        $queue->public_token_rotated_at = now();
    }
}
