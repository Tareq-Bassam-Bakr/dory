<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\MembershipStatus;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business on DORY: the tenant boundary. Every business-owned record added
 * in later phases (branches, services, queues) hangs off a business.
 *
 * @property int $id Internal key. Never exposed by the API.
 * @property string $public_id ULID exposed by the API.
 * @property BusinessStatus $status
 */
#[Fillable(['name', 'slug', 'description'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
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
        ];
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
