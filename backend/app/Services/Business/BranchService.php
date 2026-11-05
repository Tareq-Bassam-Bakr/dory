<?php

namespace App\Services\Business;

use App\Models\Branch;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

/**
 * Creates, changes and deletes branches. Authorization happens before these
 * methods are called (BranchPolicy / BusinessPolicy).
 */
final class BranchService
{
    /**
     * @param  array<string, mixed>  $attributes  Validated branch fields.
     */
    public function create(Business $business, array $attributes): Branch
    {
        $branch = new Branch($attributes + ['timezone' => $business->timezone]);
        $branch->business()->associate($business);
        $branch->saveOrFail();

        return $branch;
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
