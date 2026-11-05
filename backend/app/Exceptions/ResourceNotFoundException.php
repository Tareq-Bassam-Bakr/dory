<?php

namespace App\Exceptions;

/**
 * A tenant-owned resource does not exist OR the user may not reach it.
 *
 * Both cases return the same 404 (same message, same code), so users cannot
 * discover resources of other tenants or branches.
 */
abstract class ResourceNotFoundException extends ApiException
{
    public function status(): int
    {
        return 404;
    }
}
