<?php

namespace App\Services\Intelligence;

/**
 * One finding about a business, computed from its own records
 * (docs/AI_ARCHITECTURE.md). The facts are numbers and labels only; a
 * narrator turns them into words.
 */
final readonly class Insight
{
    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_SUGGESTION = 'suggestion';

    public const SEVERITY_POSITIVE = 'positive';

    /**
     * @param  string  $kind  e.g. no_show_high (lang/{en,ar}/insights.php).
     * @param  array<string, int|float|string>  $facts  Placeholders for the texts.
     */
    public function __construct(
        public string $kind,
        public string $severity,
        public array $facts,
    ) {}
}
