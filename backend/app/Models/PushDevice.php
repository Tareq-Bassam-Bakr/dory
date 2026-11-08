<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A device that receives push notifications, for a signed-in user or for one
 * guest ticket (docs/DEVICE_TOKEN_SECURITY.md). Created only through
 * PushDeviceRegistry; nothing here is fillable.
 *
 * The FCM token is encrypted at rest (APP_KEY) and never serialized.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $user_id
 * @property int|null $queue_ticket_id
 * @property string $owner_key
 * @property string $token_encrypted Decrypted on read; never logged.
 * @property string $token_hash
 * @property DevicePlatform $platform
 * @property string $locale
 * @property string|null $app_version
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 */
class PushDevice extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'user_id', 'queue_ticket_id', 'owner_key', 'token_encrypted', 'token_hash'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_encrypted' => 'encrypted',
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function ownerKeyForUser(User $user): string
    {
        return 'user:'.$user->getKey();
    }

    public static function ownerKeyForTicket(QueueTicket $ticket): string
    {
        return 'ticket:'.$ticket->getKey();
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * @param  Builder<PushDevice>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<QueueTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }
}
