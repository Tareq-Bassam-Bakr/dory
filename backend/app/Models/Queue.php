<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\QueueSessionStatus;
use App\Enums\QueueStatus;
use App\Enums\ServiceStatus;
use App\Enums\TicketStatus;
use Database\Factories\QueueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The permanent queue configuration of one service in one branch. Each
 * service has its own queue, ticket counter and waiting list; one generic
 * engine serves every business category.
 *
 * branch_id and service_id are never fillable: a queue cannot be moved to
 * another branch or tenant through input.
 *
 * @property int $id
 * @property string $public_id
 * @property int $branch_id
 * @property int $service_id
 * @property string $ticket_prefix
 * @property QueueStatus $status
 * @property int|null $capacity
 * @property int|null $estimated_service_duration Minutes.
 * @property int $grace_period_seconds
 */
#[Fillable([
    'name', 'ticket_prefix', 'status', 'capacity', 'allow_guest_join', 'require_guest_name',
    'require_guest_phone', 'estimated_service_duration', 'grace_period_seconds', 'settings',
])]
#[Hidden(['id', 'branch_id', 'service_id', 'public_token_hash', 'public_token_encrypted', 'active_prefix', 'active_slot'])]
class Queue extends Model
{
    /** @use HasFactory<QueueFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'allow_guest_join' => true,
        'require_guest_name' => true,
        'require_guest_phone' => false,
        'grace_period_seconds' => 120,
        'lane' => 1,
    ];

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
            'status' => QueueStatus::class,
            'capacity' => 'integer',
            'allow_guest_join' => 'boolean',
            'require_guest_name' => 'boolean',
            'require_guest_phone' => 'boolean',
            'estimated_service_duration' => 'integer',
            'grace_period_seconds' => 'integer',
            'settings' => 'array',
            'public_token_encrypted' => 'encrypted',
            'public_token_rotated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Database-level uniqueness among live queues (see the migration).
        static::saving(function (Queue $queue): void {
            $live = ! $queue->trashed();
            $queue->active_prefix = $live ? $queue->branch_id.':'.$queue->ticket_prefix : null;
            $queue->active_slot = $live ? $queue->service_id.':'.$queue->lane : null;
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * @return HasMany<QueueSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(QueueSession::class);
    }

    /**
     * The open or paused session, if any.
     *
     * @return HasOne<QueueSession, $this>
     */
    public function activeSession(): HasOne
    {
        return $this->hasOne(QueueSession::class)->whereNotNull('active_key');
    }

    /**
     * @return HasMany<QueueTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * @return HasMany<QueueStaffAssignment, $this>
     */
    public function staffAssignments(): HasMany
    {
        return $this->hasMany(QueueStaffAssignment::class);
    }

    /**
     * @return HasMany<QueueStaffAssignment, $this>
     */
    public function activeStaffAssignments(): HasMany
    {
        return $this->hasMany(QueueStaffAssignment::class)->whereNull('unassigned_at');
    }

    public function isActive(): bool
    {
        return $this->status === QueueStatus::Active;
    }

    /**
     * Why customers cannot use this queue because of what it belongs to:
     * "business_unavailable", "branch_inactive", "service_unavailable", or
     * null when the business, branch and service are all available. Expects
     * branch.business and service. Used by the join guard and by every
     * public view, so the two can never disagree.
     */
    public function unavailabilityReason(): ?string
    {
        $business = $this->branch?->business;

        return match (true) {
            $business === null, $business->status !== BusinessStatus::Active => 'business_unavailable',
            ! $this->branch->isActive() => 'branch_inactive',
            $this->service === null, $this->service->trashed(), ! $this->service->isActive() => 'service_unavailable',
            default => null,
        };
    }

    /**
     * Live queues with an open session: not paused, not closed. Use with
     * branch and business checks; see DiscoveryQueries.
     *
     * @param  Builder<Queue>  $query
     */
    #[Scope]
    protected function openForJoining(Builder $query): void
    {
        $query->where('queues.status', QueueStatus::Active->value)
            ->whereHas('service', function (Builder $service): void {
                $service->where('services.status', ServiceStatus::Active->value)->whereNull('services.deleted_at');
            })
            ->whereHas('activeSession', function (Builder $session): void {
                $session->where('queue_sessions.status', QueueSessionStatus::Open->value);
            });
    }

    /**
     * Open and not full: a new ticket would be accepted right now. Capacity
     * counts waiting tickets of the active session, as TicketJoinService does.
     *
     * @param  Builder<Queue>  $query
     */
    #[Scope]
    protected function acceptingTickets(Builder $query): void
    {
        $query->openForJoining()->where(function (Builder $room): void {
            $room->whereNull('queues.capacity')->orWhereRaw(
                'queues.capacity > (select count(*) from queue_tickets
                    inner join queue_sessions on queue_sessions.id = queue_tickets.queue_session_id
                    where queue_sessions.queue_id = queues.id
                    and queue_sessions.active_key is not null
                    and queue_tickets.status = ?)',
                [TicketStatus::Waiting->value],
            );
        });
    }

    /**
     * Minutes per customer used when there is not enough history.
     */
    public function configuredServiceMinutes(): int
    {
        return $this->estimated_service_duration
            ?? $this->service?->estimated_duration_minutes
            ?? 15;
    }
}
