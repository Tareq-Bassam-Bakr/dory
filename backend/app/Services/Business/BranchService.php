<?php

namespace App\Services\Business;

use App\Enums\PlanLimit;
use App\Models\Branch;
use App\Models\Business;
use App\Services\Billing\Entitlements;
use Illuminate\Support\Facades\DB;

/**
 * Creates, changes and deletes branches. Authorization happens before these
 * methods are called (BranchPolicy / BusinessPolicy).
 */
final class BranchService
{
    public function __construct(private readonly Entitlements $entitlements) {}

    /**
     * Refused when the business's plan has no branch left
     * (docs/SUBSCRIPTIONS.md).
     *
     * @param  array<string, mixed>  $attributes  Validated branch fields.
     */
    public function create(Business $business, array $attributes): Branch
    {
        return DB::transaction(function () use ($business, $attributes): Branch {
            $this->entitlements->ensureCanAdd($business, PlanLimit::Branches);

            $branch = new Branch($attributes + ['timezone' => $business->timezone]);
            $branch->business()->associate($business);
            $branch->saveOrFail();

            return $branch;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated branch fields.
     */
    public function update(Branch $branch, array $attributes): Branch
    {
        $branch->fill($attributes)->saveOrFail();

        return $branch;
    }

    /**
     * Soft-deletes the branch and archives its services, in one transaction.
     * Rows are kept for future queue and ticket history.
     */
    public function delete(Branch $branch): void
    {
        DB::transaction(function () use ($branch): void {
            $branch->services()->each(fn ($service) => $service->deleteOrFail());
            $branch->deleteOrFail();
        });
    }
}
