<?php

namespace App\Models;

use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use Database\Factories\BusinessMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's membership in one business, with a business-scoped role.
 *
 * business_id and user_id are deliberately not fillable: memberships are
 * created only by BusinessMembershipService, which associates both models.
 *
 * @property BusinessRole $role
 * @property MembershipStatus $status
 */
#[Fillable(['role', 'status', 'joined_at'])]
class BusinessMember extends Model
{
    /** @use HasFactory<BusinessMemberFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => BusinessRole::class,
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<BusinessMember>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active->value);
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }
}
