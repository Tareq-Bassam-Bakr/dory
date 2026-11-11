<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\MembershipStatus;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A business on DORY: the tenant boundary. Branches, services, members and
 * invitations all hang off a business.
 *
 * Ownership lives in business_members (role "owner"), not in created_by.
 * Deleting a business archives it: status "archived" plus a soft delete,
 * so its history survives.
 *
 * @property int $id Internal key. Never exposed by the API.
 * @property string $public_id ULID exposed by the API.
 * @property BusinessStatus $status
 * @property string $timezone
 * @property float|null $rating_average Derived from ticket_reviews, never fillable.
 * @property int $rating_count
 */
#[Fillable(['name', 'slug', 'description', 'email', 'phone', 'website', 'timezone'])]
#[Hidden(['id', 'category_id', 'created_by', 'idempotency_key'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'timezone' => 'UTC',
        'rating_count' => 0,
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
            'status' => BusinessStatus::class,
            'rating_average' => 'float',
            'rating_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BusinessCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BusinessMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    /**
     * The business's subscription (docs/SUBSCRIPTIONS.md).
     *
     * @return HasOne<BusinessSubscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(BusinessSubscription::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Services of every (non-deleted) branch of the business.
     *
     * @return HasManyThrough<Service, Branch, $this>
     */
    public function services(): HasManyThrough
    {
        return $this->hasManyThrough(Service::class, Branch::class);
    }

    /**
     * @return HasMany<BusinessInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(BusinessInvitation::class);
    }

    /**
     * @return HasMany<BusinessAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(BusinessAuditLog::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path === null ? null : Storage::disk('public')->url($this->cover_path);
    }

    /**
     * Customers who saved this business.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_favorites');
    }

    /**
     * Whether members may change the business, its branches, services and team.
     */
    public function acceptsChanges(): bool
    {
        return $this->status->acceptsChanges();
    }

    /**
     * What the public may see (docs/BUSINESS_DISCOVERY.md): an active
     * business with at least one active branch. Inactive, suspended and
     * archived businesses never appear in discovery.
     *
     * @param  Builder<Business>  $query
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->where('businesses.status', BusinessStatus::Active->value)
            ->whereHas('branches', function (Builder $branches): void {
                $branches->publiclyVisible();
            });
    }

    /**
     * Limit a query to businesses where the user has an active membership.
     * Use this for every business listing; never filter by a client-supplied id.
     *
     * @param  Builder<Business>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): void
    {
        $query->whereHas('members', function (Builder $members) use ($user): void {
            $members->where('user_id', $user->getKey())
                ->where('status', MembershipStatus::Active->value);
        });
    }
}
