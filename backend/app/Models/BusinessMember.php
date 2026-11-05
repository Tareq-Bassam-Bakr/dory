<?php

namespace App\Models;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use Database\Factories\BusinessMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A user's membership in one business, with a business-scoped role and
 * branch scope.
 *
 * business_id and user_id are deliberately not fillable: memberships are
 * created only by BusinessMembershipService, which associates both models.
 *
 * @property string $public_id ULID exposed by the API.
 * @property BusinessRole $role
 * @property MembershipStatus $status
 * @property BranchAccess $branch_access
 */
#[Fillable(['role', 'status', 'branch_access', 'joined_at'])]
#[Hidden(['id', 'business_id', 'user_id'])]
class BusinessMember extends Model
{
    /** @use HasFactory<BusinessMemberFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'branch_access' => 'all',
    ];

    /**
     * Only public_id is a ULID; the primary key stays an integer.
     *
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
            'status' => MembershipStatus::class,
            'branch_access' => BranchAccess::class,
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
     * Branches assigned to a membership with "selected" branch access.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'business_member_branch')->withTimestamps();
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

    public function isOwner(): bool
    {
        return $this->role === BusinessRole::Owner;
    }

    /**
     * Owners always reach every branch; others only with "all" access.
     */
    public function hasAllBranchAccess(): bool
    {
        return $this->isOwner() || $this->branch_access === BranchAccess::All;
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if (! $this->grantsAccess() || $branch->business_id !== $this->business_id) {
            return false;
        }

        if ($this->hasAllBranchAccess()) {
            return true;
        }

        if ($this->relationLoaded('branches')) {
            return $this->branches->contains($branch->getKey());
        }

        return $this->branches()->whereKey($branch->getKey())->exists();
    }
}
