<?php

namespace App\Enums;

/**
 * What a plan limits (docs/SUBSCRIPTIONS.md). Limits apply to adding
 * resources, never to using the ones that exist.
 */
enum PlanLimit: string
{
    case Branches = 'branches';
    case Queues = 'queues';
    case Members = 'members';

    public function column(): string
    {
        return 'max_'.$this->value;
    }
}
