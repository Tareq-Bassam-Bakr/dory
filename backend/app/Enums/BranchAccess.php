<?php

namespace App\Enums;

/**
 * Which branches a membership reaches.
 *
 * - all:      every branch of the business, including future ones.
 * - selected: only the branches assigned in business_member_branch. With
 *             no assignments, no branch is reachable (never "all").
 *
 * Owners always reach every branch, whatever this value says.
 */
enum BranchAccess: string
{
    case All = 'all';
    case Selected = 'selected';
}
