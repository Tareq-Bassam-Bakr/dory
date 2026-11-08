<?php

namespace App\Jobs;

use App\Models\PushDevice;
use App\Services\Push\PushDeviceRegistry;
use App\Services\Push\PushMessage;
use App\Services\Push\PushOutcome;
use App\Services\Push\PushSender;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one notification to one device (docs/PUSH_NOTIFICATIONS.md).
 *
 * - The job holds the device id, never the token: the token is decrypted
 *   only when sending, and never logged.
 * - Unique per notification and device, so the same message is not queued
 *   twice; a retry after a temporary failure resends only to this device.
 * - An invalid token revokes every registration of it.
 * - "Accepted" means FCM took the message: FCM does not confirm delivery.
 */
final class SendPushMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public int $deviceId,
        public string $title,
        public string $body,
        public array $data,
        public bool $urgent,
        public string $key,
    ) {
        $this->onQueue((string) config('dory.notifications.queue'));
    }

    public function uniqueId(): string
    {
        return $this->key;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(PushSender $sender, PushDeviceRegistry $registry): void
    {
        $device = PushDevice::query()->find($this->deviceId);
        if ($device === null || ! $device->isActive()) {
            return;
        }

        $outcome = $sender->send(new PushMessage(
            $device->token_encrypted,
            $this->title,
            $this->body,
            $this->data,
            $this->urgent,
        ));

        match ($outcome) {
            PushOutcome::InvalidToken => $this->revoke($registry, $device),
            PushOutcome::Retry => $this->retryLater($device),
            PushOutcome::Failed => Log::warning('Push message refused by FCM.', [
                'device' => $device->public_id,
                'kind' => $this->data['kind'] ?? null,
            ]),
            PushOutcome::Accepted, PushOutcome::Skipped => null,
        };
    }

    public function failed(?Throwable $exception): void
    {
        // The class only: messages of HTTP exceptions can contain request data.
        Log::warning('Push delivery failed.', [
            'device_row' => $this->deviceId,
            'kind' => $this->data['kind'] ?? null,
            'error' => $exception === null ? null : $exception::class,
        ]);
    }

    private function revoke(PushDeviceRegistry $registry, PushDevice $device): void
    {
        $registry->revokeToken($device->token_hash);
        Log::info('Push token rejected by FCM; device revoked.', ['device' => $device->public_id]);
    }

    private function retryLater(PushDevice $device): void
    {
        if ($this->attempts() >= $this->tries) {
            Log::warning('Push delivery gave up after retries.', ['device' => $device->public_id]);

            return;
        }
        $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
    }
}
