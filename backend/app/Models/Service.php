<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something a branch offers: a consultation, a counter operation, a haircut.
 *
 * Deliberately generic: there is no per-sector service type. branch_id is
 * never fillable, so a service cannot be moved to another branch or tenant
 * through input.
 *
 * @property int $id Internal key. Never exposed by the API.
 * @property string $public_id ULID exposed by the API.
 * @property int $branch_id
 * @property ServiceStatus $status
 * @property int $estimated_duration_minutes
 * @property int $display_order
 */
#[Fillable(['name', 'description', 'estimated_duration_minutes', 'display_order', 'status'])]
#[Hidden(['id', 'branch_id'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'estimated_duration_minutes' => 15,
        'display_order' => 0,
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
            'status' => ServiceStatus::class,
            'estimated_duration_minutes' => 'integer',
            'display_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<Queue, $this>
     */
    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }
}
