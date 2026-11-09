<?php

namespace App\Services\Intelligence;

use App\Contracts\InsightNarrator;

/**
 * The built-in narrator: translated templates (lang/{en,ar}/insights.php).
 * Deterministic, free, and works offline.
 */
final class TemplateInsightNarrator implements InsightNarrator
{
    public function narrate(Insight $insight, string $locale): array
    {
        $replace = array_map(fn (int|float|string $value): string => (string) $value, $insight->facts);
        if ($locale === 'ar' && isset($replace['hours'])) {
            // Arabic comma, and a right-to-left mark so each time stays its
            // own run instead of the times and commas reading as one block
            // (seen on a phone).
            $replace['hours'] = str_replace(', ', "\u{060C}\u{200F} ", $replace['hours']);
        }

        return [
            'title' => __('insights.'.$insight->kind.'.title', $replace, $locale),
            'body' => __('insights.'.$insight->kind.'.body', $replace, $locale),
        ];
    }

    public function name(): string
    {
        return 'template';
    }
}
