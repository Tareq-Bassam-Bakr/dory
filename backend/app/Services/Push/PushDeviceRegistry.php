<?php

namespace App\Services\Push;

use App\Enums\DevicePlatform;
use App\Exceptions\NotificationException;
use App\Models\PushDevice;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Registers and revokes push devices (docs/DEVICE_TOKEN_SECURITY.md).
 *
 * - A device is registered for the authenticated user, or for one guest
 *   ticket proven by its access token. Nobody can register for someone else.
 * - A token can belong to one user at a time: when a phone signs in to
 *   another account, the previous account's registration of the same token
 *   is revoked, so it stops receiving that account's notifications.
 * - Registering the same token again updates the row (idempotent).
 * - A new token can replace an old one (rotation): the old row is revoked,
 *   only if it belongs to the same owner.
 */
final class PushDeviceRegistry
{
    /**
     * @param  array{token: string, platform: DevicePlatform, locale: string, app_version: ?string, replaces: ?string}  $device
     */
    public function registerForUser(User $user, array $device): PushDevice
    {
        $ownerKey = PushDevice::ownerKeyForUser($user);
        $hash = PushDevice::hashToken($device['token']);

        return DB::transaction(function () use ($user, $device, $ownerKey, $hash): PushDevice {
            // The phone now belongs to this account.
            PushDevice::query()
                ->where('token_hash', $hash)
                ->whereNotNull('user_id')
                ->where('user_id', '!=', $user->getKey())
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            $this->revokeReplaced($device['replaces'], fn ($query) => $query->where('user_id', $user->getKey()));

            return $this->upsert($ownerKey, $hash, $device, ['user_id' => $user->getKey()]);
        });
    }

    /**
     * @param  array{token: string, platform: DevicePlatform, locale: string, app_version: ?string, replaces: ?string}  $device
     */
    public function registerForGuestTicket(QueueTicket $ticket, array $device): PushDevice
    {
        if (! $ticket->status->isActive()) {
            throw NotificationException::ticketFinished();
        }

        $ownerKey = PushDevice::ownerKeyForTicket($ticket);
        $hash = PushDevice::hashToken($device['token']);

        return DB::transaction(function () use ($ticket, $device, $ownerKey, $hash): PushDevice {
            $this->revokeReplaced($device['replaces'], fn ($query) => $query->where('queue_ticket_id', $ticket->getKey()));

            return $this->upsert($ownerKey, $hash, $device, ['queue_ticket_id' => $ticket->getKey()]);
        });
    }

    public function revoke(PushDevice $device): void
    {
        if ($device->revoked_at === null) {
            $device->revoked_at = now();
            $device->saveOrFail();
        }
    }

    /**
     * FCM rejected this token: no owner can use it any more.
     */
    public function revokeToken(string $tokenHash): void
    {
        PushDevice::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  callable(Builder<PushDevice>): mixed  $sameOwner
     */
    private function revokeReplaced(?string $publicId, callable $sameOwner): void
    {
        if ($publicId === null) {
            return;
        }
        $query = PushDevice::query()->where('public_id', $publicId)->whereNull('revoked_at');
        $sameOwner($query);
        $query->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array{token: string, platform: DevicePlatform, locale: string, app_version: ?string, replaces: ?string}  $device
     * @param  array<string, int>  $owner
     */
    private function upsert(string $ownerKey, string $hash, array $device, array $owner): PushDevice
    {
        $row = PushDevice::query()
            ->where('token_hash', $hash)
            ->where('owner_key', $ownerKey)
            ->lockForUpdate()
            ->first();

        $row ??= new PushDevice;
        $row->forceFill($owner + [
            'owner_key' => $ownerKey,
            'token_hash' => $hash,
            'platform' => $device['platform'],
            'locale' => $device['locale'],
            'app_version' => $device['app_version'],
            'last_seen_at' => now(),
            'revoked_at' => null,
        ]);
        $row->token_encrypted = $device['token'];

        try {
            $row->saveOrFail();
        } catch (UniqueConstraintViolationException) {
            // Two registrations of the same token raced: the other one won.
            return PushDevice::query()->where('token_hash', $hash)->where('owner_key', $ownerKey)->firstOrFail();
        }

        return $row;
    }
}
