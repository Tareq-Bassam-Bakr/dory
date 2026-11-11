<?php

namespace App\Enums;

/**
 * Business-side features a plan unlocks (docs/SUBSCRIPTIONS.md). Customer
 * features are never behind a plan.
 */
enum PlanFeature: string
{
    // Business insights and peak times (Phase 07).
    case Insights = 'insights';

    // Insights and peak times for one branch at a time.
    case BranchInsights = 'branch_insights';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $feature): string => $feature->value, self::cases());
    }
}
