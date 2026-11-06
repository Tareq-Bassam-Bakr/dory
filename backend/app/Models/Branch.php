<?php

namespace App\Models;

use App\Enums\BranchStatus;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical location of a business. Every branch belongs to exactly one
 * business; business_id is never fillable, so a branch cannot be moved to
 * another tenant through input.
 *
 * Coordinates are WGS 84 decimal degrees (OpenStreetMap compatible).
 *
 * @property int $id Internal key. Never exposed by the API.
 * @property string $public_id ULID exposed by the API.
 * @property int $business_id
 * @property BranchStatus $status
 * @property float|null $latitude
 * @property float|null $longitude
 */
#[Fillable([
    'name', 'address', 'country', 'city', 'region', 'latitude', 'longitude',
    'phone', 'email', 'timezone', 'status',
])]
#[Hidden(['id', 'business_id'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'timezone' => 'UTC',
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
            'status' => BranchStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
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
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function isActive(): bool
    {
        return $this->status === BranchStatus::Active;
    }

    /**
     * @return HasMany<Queue, $this>
     */
    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }
}
