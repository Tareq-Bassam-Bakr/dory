<?php

namespace App\Models;

use App\Enums\PlatformAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One sensitive platform operation (docs/ADMIN_DASHBOARD.md, "Audit log").
 * Append-only: the application never updates or deletes these rows.
 *
 * @property int $id
 * @property ?int $actor_id
 * @property ?string $actor_label
 * @property PlatformAuditAction $action
 * @property ?int $business_id
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?array<string, mixed> $properties
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property Carbon $created_at
 * @property-read ?User $actor
 * @property-read ?Business $business
 */
class PlatformAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'action' => PlatformAuditAction::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }
}
