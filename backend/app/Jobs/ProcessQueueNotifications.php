<?php

namespace App\Jobs;

use App\Services\Notifications\QueueNotificationPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the notifications of one recorded queue change, in the background
 * and after the change was committed. A failure here never undoes the
 * change itself; retries are safe because every alert is sent at most once
 * (NotificationDispatcher).
 */
final class ProcessQueueNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $queueEventId)
    {
        $this->onQueue((string) config('dory.notifications.queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(QueueNotificationPlanner $planner): void
    {
        $planner->handle($this->queueEventId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Queue notifications failed.', [
            'queue_event' => $this->queueEventId,
            'error' => $exception === null ? null : $exception::class,
        ]);
    }
}
