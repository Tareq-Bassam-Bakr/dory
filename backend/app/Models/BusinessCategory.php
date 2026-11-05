<?php

namespace App\Models;

use Database\Factories\BusinessCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of business (restaurants, clinics, banks, ...).
 *
 * Categories describe and group businesses only. No business logic may
 * branch on a category: every sector uses the same branches, services and
 * (later) queues.
 *
 * The slug is the public identifier and never changes. Names are stored
 * per language: ['en' => '...', 'ar' => '...'].
 *
 * @property int $id Internal key. Never exposed by the API.
 * @property string $slug
 * @property array<string, string> $name
 * @property bool $is_active
 */
#[Fillable(['slug', 'name', 'icon', 'sort_order', 'is_active'])]
class BusinessCategory extends Model
{
    /** @use HasFactory<BusinessCategoryFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'icon' => 'other',
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<BusinessCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<BusinessCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Business, $this>
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'category_id');
    }

    /**
     * @param  Builder<BusinessCategory>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<BusinessCategory>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
