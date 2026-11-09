<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Busy and quiet periods from real arrivals (docs/AI_ARCHITECTURE.md).
 *
 * For every weekday and hour (branch local time), the average number of
 * customers who joined, over the days observed: from the first ticket in the
 * window to today, closed days included as zero. The same weekday and hour
 * is expected to look alike next week; that is the forecast.
 *
 * Levels compare an hour with the business's own active hours: "busy" at or
 * above their 75th percentile, "quiet" at or below the 25th, "closed" when
 * nobody ever joined then.
 *
 * Below dory.intelligence.forecast_minimum_tickets tickets or
 * forecast_minimum_days days, the result says so and makes no forecast.
 */
final class PeakTimeForecaster
{
    /**
     * @param  Collection<int, object{joined_local: Carbon}>  $tickets
     * @return array<string, mixed>
     */
    public function forecast(Collection $tickets, string $timezone): array
    {
        $today = now($timezone)->startOfDay();
        $minimum = [
            'tickets' => (int) config('dory.intelligence.forecast_minimum_tickets'),
            'days' => (int) config('dory.intelligence.forecast_minimum_days'),
        ];

        $first = $tickets->min(fn (object $ticket): string => $ticket->joined_local->toDateString());
        $start = $first === null ? $today : Carbon::parse($first, $timezone)->startOfDay();
        $days = (int) $start->diffInDays($today) + 1;

        $result = [
            'status' => 'insufficient_data',
            'timezone' => $timezone,
            'tickets' => $tickets->count(),
            'days_covered' => $first === null ? 0 : $days,
            'minimum' => $minimum,
            'weekdays' => [],
            'busiest' => [],
            'quietest' => [],
            'upcoming' => [],
        ];
        if ($tickets->count() < $minimum['tickets'] || $result['days_covered'] < $minimum['days']) {
            return $result;
        }

        // How many of each weekday the window contains (1 = Monday).
        $occurrences = array_fill(1, 7, 0);
        for ($day = $start->copy(); $day->lte($today); $day->addDay()) {
            $occurrences[$day->dayOfWeekIso]++;
        }

        $counts = array_fill(1, 7, array_fill(0, 24, 0));
        foreach ($tickets as $ticket) {
            $counts[$ticket->joined_local->dayOfWeekIso][$ticket->joined_local->hour]++;
        }

        $averages = [];
        foreach ($counts as $weekday => $hours) {
            foreach ($hours as $hour => $count) {
                $averages[$weekday][$hour] = $occurrences[$weekday] > 0 ? round($count / $occurrences[$weekday], 1) : 0.0;
            }
        }

        $active = collect($averages)->flatten()->filter(fn (float $value): bool => $value > 0)->sort()->values();
        $low = self::percentile($active, 0.25);
        $high = self::percentile($active, 0.75);
        // Every open hour alike: none is busier than another.
        $spread = $high > $low;
        $level = fn (float $value): string => match (true) {
            $value <= 0 => 'closed',
            $spread && $value >= $high => 'busy',
            $spread && $value <= $low => 'quiet',
            default => 'normal',
        };

        $slots = collect($averages)->flatMap(fn (array $hours, int $weekday) => collect($hours)
            ->map(fn (float $average, int $hour): array => ['weekday' => $weekday, 'hour' => $hour, 'average' => $average]))
            ->values();
        $open = $slots->filter(fn (array $slot): bool => $slot['average'] > 0);

        $result['status'] = 'ready';
        $result['weekdays'] = collect($averages)->map(fn (array $hours, int $weekday): array => [
            'weekday' => $weekday,
            'hours' => array_values($hours),
            'levels' => array_map($level, array_values($hours)),
            'total' => round(array_sum($hours), 1),
        ])->values()->all();
        $result['busiest'] = $open->sortByDesc('average')->take(3)->values()->all();
        $result['quietest'] = $open->sortBy('average')->take(3)->values()->all();
        $result['upcoming'] = collect(range(0, 6))->map(function (int $offset) use ($today, $averages): array {
            $date = $today->copy()->addDays($offset);
            $hours = $averages[$date->dayOfWeekIso];
            $peak = max($hours);

            return [
                'date' => $date->toDateString(),
                'weekday' => $date->dayOfWeekIso,
                'expected' => round(array_sum($hours), 1),
                'peak_hour' => $peak > 0 ? array_search($peak, $hours, true) : null,
            ];
        })->all();

        return $result;
    }

    /**
     * @param  Collection<int, float>  $sorted
     */
    private static function percentile(Collection $sorted, float $fraction): float
    {
        if ($sorted->isEmpty()) {
            return 0.0;
        }
        $position = ($sorted->count() - 1) * $fraction;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($position - $lower);
    }
}
