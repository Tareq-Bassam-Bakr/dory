<?php

namespace App\Http\Requests\Services\Concerns;

use App\Models\Branch;
use App\Models\Service;
use Closure;

/**
 * Service names are unique within a branch (ignoring case and archived
 * services). The same name may exist in other branches.
 */
trait ValidatesServiceName
{
    /**
     * @return Closure(string, mixed, Closure): void
     */
    protected function uniqueNameInBranch(Branch $branch, ?Service $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($branch, $ignore): void {
            if (! is_string($value)) {
                return;
            }

            $taken = Service::query()
                ->where('branch_id', $branch->getKey())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail(__('api.service.name_taken'));
            }
        };
    }
}
