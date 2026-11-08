<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person using DORY.
 *
 * A user has no permanent role: they act as a customer by default and gain
 * business roles only through business memberships. Platform administration
 * is a separate flag that mass assignment can never set.
 *
 * @property int $id Internal key. Never exposed by the API.
 * @property string $public_id ULID exposed by the API.
 * @property UserStatus $status
 * @property bool $is_platform_admin
 */
#[Fillable(['name', 'email', 'phone', 'password', 'locale', 'timezone'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    /**
     * In-memory defaults, matching the database column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'en',
        'timezone' => 'UTC',
        'status' => 'active',
        'is_platform_admin' => false,
    ];

    /**
     * Columns that receive an auto-generated ULID. The primary key stays an
     * auto-increment integer.
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * @return HasMany<BusinessMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    /**
     * Businesses where the user holds an active membership.
     *
     * @return BelongsToMany<Business, $this>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_members')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('status', MembershipStatus::Active->value);
    }

    /**
     * Businesses this customer saved. Unrelated to memberships.
     *
     * @return BelongsToMany<Business, $this>
     */
    public function favoriteBusinesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'customer_favorites');
    }

    /**
     * Devices that receive this user's push notifications.
     *
     * @return HasMany<PushDevice, $this>
     */
    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    /**
     * Notifications broadcast in real time go to the user's own private
     * channel, named by public id (docs/BROADCAST_CHANNELS.md).
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'users.'.$this->public_id;
    }

    public function isActive(): bool
    {
        return $this->status->canAuthenticate();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin;
    }
}
