<?php

namespace App\Http\Resources\Concerns;

use App\Services\Business\BusinessAbilities;
use App\Services\Business\CurrentBusinessContext;

/**
 * Lets tenant resources describe what the current user may do with them.
 *
 * The abilities come from the request's CurrentBusinessContext, filled by
 * the tenant route bindings. They are hints for the client UI only: the
 * server re-checks every action through the policies.
 */
trait ResolvesViewerAbilities
{
    protected function viewerAbilities(int $businessId): ?BusinessAbilities
    {
        $context = app(CurrentBusinessContext::class);

        if (! $context->isResolved() || $context->business()->getKey() !== $businessId) {
            return null;
        }

        return $context->abilities();
    }
}
