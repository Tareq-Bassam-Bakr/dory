<?php

namespace App\Services\Catalog;

use App\Models\Branch;
use App\Models\Service;

/**
 * Creates, changes and archives the services a branch offers. One generic
 * implementation for every business category. Authorization happens before
 * these methods are called (BranchPolicy / ServicePolicy).
 */
final class ServiceCatalogService
{
    /**
     * @param  array<string, mixed>  $attributes  Validated service fields.
     */
    public function create(Branch $branch, array $attributes): Service
    {
        $service = new Service($attributes);
        $service->branch()->associate($branch);

        if (! array_key_exists('display_order', $attributes)) {
            // New services go to the end of the list.
            $service->display_order = (int) $branch->services()->withTrashed()->max('display_order') + 1;
        }

        $service->saveOrFail();

        return $service;
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated service fields.
     */
    public function update(Service $service, array $attributes): Service
    {
        $service->fill($attributes)->saveOrFail();

        return $service;
    }

    /**
     * Archives the service (soft delete): it disappears from every list but
     * its row stays for future ticket history.
     */
    public function archive(Service $service): void
    {
        $service->deleteOrFail();
    }
}
