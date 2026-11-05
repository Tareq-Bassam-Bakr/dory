<?php

namespace App\Enums;

/**
 * Whether a branch is open for business. Deleted branches are soft-deleted.
 */
enum BranchStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
