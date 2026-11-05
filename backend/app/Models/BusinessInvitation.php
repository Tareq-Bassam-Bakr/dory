<?php

namespace App\Models;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use Database\Factories\BusinessInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invitation to join a business team.
 *
 * Only a SHA-256 hash of the token is stored; the plain token exists only
 * in the invitation link. Invitations are created and resolved by
 * InvitationService.
 *
 * @property string $public_id
 * @property int $business_id
 * @property string $email
 * @property BusinessRole $role
 * @property BranchAccess $branch_access
 * @property list<int>|null $branch_ids
 * @property InvitationStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $responded_at
 */
#[Fillable(['email', 'role', 'branch_access', 'branch_ids', 'expires_at'])]
#[Hidden(['id', 'business_id', 'token_hash', 'pending_key', 'branch_ids', 'invited_by', 'accepted_by'])]
class BusinessInvitation extends Model
{
    /** @use HasFactory<BusinessInvitationFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'branch_access' => 'all',
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
            'role' => BusinessRole::class,
            'branch_access' => BranchAccess::class,
            'branch_ids' => 'array',
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function pendingKeyFor(Business $business, string $email): string
    {
        return hash('sha256', $business->getKey().'|'.mb_strtolower($email));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * The status clients see: a pending invitation past its expiry is expired,
     * even before a write has recorded it.
     */
    public function effectiveStatus(): InvitationStatus
    {
        return $this->status === InvitationStatus::Pending && $this->isExpired()
            ? InvitationStatus::Expired
            : $this->status;
    }

    public function isOpen(): bool
    {
        return $this->effectiveStatus() === InvitationStatus::Pending;
    }
}
