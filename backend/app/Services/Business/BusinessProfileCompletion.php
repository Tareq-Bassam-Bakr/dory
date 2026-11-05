<?php

namespace App\Services\Business;

use App\Enums\BranchStatus;
use App\Enums\ServiceStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;

/**
 * How complete a business profile is, so owners know what is left before
 * customers can use it.
 *
 * Steps: basics (name and category), description, contact (email or
 * phone), branch (at least one active branch), service (at least one
 * active service in an active branch).
 */
final readonly class BusinessProfileCompletion
{
    public const STEPS = ['basics', 'description', 'contact', 'branch', 'service'];

    /**
     * @param  list<string>  $missing
     */
    private function __construct(public array $missing) {}

    /**
     * Adds the counts this class needs to a business query.
     *
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    public static function withCounts(Builder $query): Builder
    {
        return $query->withCount([
            'branches',
            'services',
            'members' => fn (Builder $members) => $members->active(),
            'branches as active_branches_count' => fn (Builder $branches) => $branches
                ->where('status', BranchStatus::Active->value),
            'services as active_services_count' => fn (Builder $services) => $services
                ->where('services.status', ServiceStatus::Active->value)
                ->where('branches.status', BranchStatus::Active->value),
        ]);
    }

    /**
     * Loads the counts on an already retrieved business.
     */
    public static function loadCounts(Business $business): Business
    {
        return $business->loadCount([
            'branches',
            'services',
            'members' => fn (Builder $members) => $members->active(),
            'branches as active_branches_count' => fn (Builder $branches) => $branches
                ->where('status', BranchStatus::Active->value),
            'services as active_services_count' => fn (Builder $services) => $services
                ->where('services.status', ServiceStatus::Active->value)
                ->where('branches.status', BranchStatus::Active->value),
        ]);
    }

    public static function for(Business $business): self
    {
        $done = [
            'basics' => filled($business->name) && $business->category_id !== null,
            'description' => filled($business->description),
            'contact' => filled($business->email) || filled($business->phone),
            'branch' => (int) $business->getAttribute('active_branches_count') > 0,
            'service' => (int) $business->getAttribute('active_services_count') > 0,
        ];

        return new self(array_keys(array_filter($done, fn (bool $isDone) => ! $isDone)));
    }

    public function percentage(): int
    {
        $total = count(self::STEPS);

        return (int) round(($total - count($this->missing)) * 100 / $total);
    }

    public function isComplete(): bool
    {
        return $this->missing === [];
    }

    /**
     * @return array{percentage: int, is_complete: bool, missing: list<string>}
     */
    public function toArray(): array
    {
        return [
            'percentage' => $this->percentage(),
            'is_complete' => $this->isComplete(),
            'missing' => $this->missing,
        ];
    }
}
