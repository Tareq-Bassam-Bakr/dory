<?php

namespace App\Models;

use App\Enums\JoinSource;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

/**
 * A customer's place in a queue session.
 *
 * Status, timestamps, staff and keys change only through
 * TicketStateMachine; none of them is fillable. Guest phone numbers are
 * encrypted at rest and the guest access token is stored only as a hash.
 *
 * @property int $id
 * @property string $public_id
 * @property int $queue_session_id
 * @property int $queue_id
 * @property int|null $user_id
 * @property string $ticket_number
 * @property int $sequence_number
 * @property Carbon $business_date
 * @property TicketStatus $status
 * @property JoinSource $join_source
 * @property string|null $guest_name
 * @property string|null $guest_phone
 * @property int|null $served_by
 * @property Carbon $joined_at
 * @property Carbon|null $called_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $ended_at
 * @property int|null $estimated_wait_seconds
 */
class QueueTicket extends Model
{
    use HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'waiting',
        'priority' => 0,
        'recall_count' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id', 'queue_session_id', 'queue_id', 'user_id', 'served_by', 'guest_phone',
        'public_access_token_hash', 'idempotency_key_hash', 'active_user_key', 'active_staff_key',
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * 128 random bits in ULID text form: unlike a time-based ULID, two
     * tickets created in the same millisecond are not adjacent values, so
     * ids cannot be predicted from one another.
     */
    public function newUniqueId(): string
    {
        return Str::lower(Ulid::fromBinary(random_bytes(16))->toBase32());
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
            'status' => TicketStatus::class,
            'join_source' => JoinSource::class,
            'business_date' => 'date',
            'guest_phone' => 'encrypted',
            'sequence_number' => 'integer',
            'priority' => 'integer',
            'recall_count' => 'integer',
            'estimated_wait_seconds' => 'integer',
            'joined_at' => 'datetime',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ended_at' => 'datetime',
            'estimated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Database-enforced invariants (see the migration).
        static::saving(function (QueueTicket $ticket): void {
            $ticket->active_user_key = $ticket->user_id !== null && $ticket->status->isActive()
                ? $ticket->queue_session_id.':'.$ticket->user_id
                : null;
            $ticket->active_staff_key = $ticket->served_by !== null && $ticket->status->occupiesStaff()
                ? (string) $ticket->served_by
                : null;
        });
    }

    /**
     * @return BelongsTo<QueueSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(QueueSession::class, 'queue_session_id');
    }

    /**
     * @return BelongsTo<Queue, $this>
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    /**
     * @return HasMany<QueueEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(QueueEvent::class, 'ticket_id');
    }

    /**
     * @return HasOne<TicketReview, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(TicketReview::class);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public static function hashAccessToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Constant-time check of a guest access token.
     */
    public function accessTokenMatches(?string $token): bool
    {
        return $token !== null
            && $token !== ''
            && $this->public_access_token_hash !== null
            && hash_equals($this->public_access_token_hash, self::hashAccessToken($token));
    }
}
