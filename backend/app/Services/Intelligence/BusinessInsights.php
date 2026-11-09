<?php

namespace App\Services\Intelligence;

use App\Contracts\InsightNarrator;
use App\Enums\TicketStatus;
use App\Models\Queue;
use Illuminate\Support\Collection;

/**
 * Performance metrics and recommendations from the business's own tickets
 * (docs/AI_ARCHITECTURE.md).
 *
 * The last insight_days days are compared with the period before. Every
 * recommendation comes from a rule with an explicit threshold, and carries
 * the numbers that triggered it; nothing is invented. Below minimum_tickets
 * tickets the metrics are still shown, but no recommendation is made.
 */
final class BusinessInsights
{
    /** Service times outside this range are clamped (as for wait times). */
    private const MIN_SERVICE_SECONDS = 30;

    private const MAX_SERVICE_SECONDS = 4 * 3600;

    private const CUSTOMER_CANCELLATIONS = ['customer_cancelled', 'guest_cancelled'];

    public function __construct(private readonly InsightNarrator $narrator) {}

    /**
     * @param  Collection<int, object>  $tickets  At least 2 × insight_days of history.
     * @return array<string, mixed>
     */
    public function analyse(Collection $tickets, string $locale): array
    {
        $days = max(1, (int) config('dory.intelligence.insight_days'));
        $minimum = (int) config('dory.intelligence.minimum_tickets');
        $now = now();
        $from = $now->copy()->subDays($days);
        $previousFrom = $from->copy()->subDays($days);

        $current = $tickets->filter(fn (object $ticket): bool => $ticket->joined_at->gte($from));
        $previous = $tickets->filter(fn (object $ticket): bool => $ticket->joined_at->gte($previousFrom) && $ticket->joined_at->lt($from));

        $metrics = $this->metrics($current);
        $metrics['previous_tickets'] = $previous->count();
        $metrics['change_percent'] = $previous->count() > 0
            ? (int) round(($current->count() - $previous->count()) / $previous->count() * 100)
            : null;

        $sufficient = $current->count() >= $minimum;
        $insights = $sufficient ? $this->rules($current, $metrics, $minimum) : [];

        return [
            'status' => $sufficient ? 'ready' : 'insufficient_data',
            'period' => [
                'days' => $days,
                'from' => $from->toIso8601String(),
                'to' => $now->toIso8601String(),
            ],
            'minimum_tickets' => $minimum,
            'metrics' => $metrics,
            'insights' => array_map(fn (Insight $insight): array => [
                'kind' => $insight->kind,
                'severity' => $insight->severity,
                ...$this->narrator->narrate($insight, $locale),
                'facts' => $insight->facts,
            ], $insights),
            'generated_by' => $this->narrator->name(),
        ];
    }

    /**
     * @param  Collection<int, object>  $tickets
     * @return array<string, int|null>
     */
    private function metrics(Collection $tickets): array
    {
        $count = $tickets->count();
        $called = $tickets->filter(fn (object $ticket): bool => $ticket->called_at !== null);
        $completed = $tickets->where('status', TicketStatus::Completed->value);
        $noShows = $tickets->where('status', TicketStatus::NoShow->value)->count();
        $cancelled = $tickets
            ->where('status', TicketStatus::Cancelled->value)
            ->filter(fn (object $ticket): bool => in_array($ticket->cancel_reason, self::CUSTOMER_CANCELLATIONS, true))
            ->count();

        $waits = $called->map(fn (object $ticket): float => $ticket->joined_at->diffInSeconds($ticket->called_at, true) / 60)->sort()->values();
        $services = $this->serviceMinutes($completed);

        return [
            'tickets' => $count,
            'completed' => $completed->count(),
            'completion_rate' => $count > 0 ? (int) round($completed->count() / $count * 100) : null,
            'no_show_rate' => $called->count() > 0 ? (int) round($noShows / $called->count() * 100) : null,
            'cancellation_rate' => $count > 0 ? (int) round($cancelled / $count * 100) : null,
            'average_wait_minutes' => $waits->isEmpty() ? null : (int) round($waits->avg()),
            'median_wait_minutes' => $waits->isEmpty() ? null : (int) round($waits->median()),
            'average_service_minutes' => $services->isEmpty() ? null : (int) round($services->avg()),
        ];
    }

    /**
     * @param  Collection<int, object>  $completed
     * @return Collection<int, float>
     */
    private function serviceMinutes(Collection $completed): Collection
    {
        return $completed
            ->filter(fn (object $ticket): bool => $ticket->started_at !== null && $ticket->completed_at !== null)
            ->map(fn (object $ticket): float => min(self::MAX_SERVICE_SECONDS, max(self::MIN_SERVICE_SECONDS,
                $ticket->started_at->diffInSeconds($ticket->completed_at, true))) / 60)
            ->values();
    }

    /**
     * @param  Collection<int, object>  $tickets
     * @param  array<string, int|null>  $metrics
     * @return list<Insight>
     */
    private function rules(Collection $tickets, array $metrics, int $minimum): array
    {
        $insights = [];
        $byHour = $tickets->countBy(fn (object $ticket): int => $ticket->joined_local->hour)->sortDesc();
        $peakHour = (int) $byHour->keys()->first();

        $called = $tickets->filter(fn (object $ticket): bool => $ticket->called_at !== null)->count();
        if ($called >= 10 && ($metrics['no_show_rate'] ?? 0) >= 15) {
            $insights[] = new Insight('no_show_high', Insight::SEVERITY_WARNING, ['rate' => $metrics['no_show_rate']]);
        }

        if (($metrics['average_wait_minutes'] ?? 0) >= 30) {
            $insights[] = new Insight('long_waits', Insight::SEVERITY_WARNING, [
                'minutes' => $metrics['average_wait_minutes'],
                'hour' => self::hour($peakHour),
            ]);
        }

        if (($metrics['cancellation_rate'] ?? 0) >= 20) {
            $insights[] = new Insight('cancellations_high', Insight::SEVERITY_WARNING, ['rate' => $metrics['cancellation_rate']]);
        }

        $topHours = $byHour->take(3);
        $share = (int) round($topHours->sum() / max(1, $tickets->count()) * 100);
        if ($byHour->count() > 3 && $share >= 45) {
            $insights[] = new Insight('peak_concentration', Insight::SEVERITY_SUGGESTION, [
                'share' => $share,
                'hours' => $topHours->keys()->sort()->map(fn (int $hour): string => self::hour($hour))->implode(', '),
            ]);
        }

        $change = $metrics['change_percent'];
        if ($change !== null && $metrics['previous_tickets'] >= $minimum) {
            if ($change >= 25) {
                $insights[] = new Insight('demand_up', Insight::SEVERITY_SUGGESTION, ['percent' => $change]);
            } elseif ($change <= -25) {
                $insights[] = new Insight('demand_down', Insight::SEVERITY_WARNING, ['percent' => abs($change)]);
            }
        }

        array_push($insights, ...$this->serviceTimeDrift($tickets));

        $hasWarning = collect($insights)->contains(fn (Insight $insight): bool => $insight->severity === Insight::SEVERITY_WARNING);
        if (! $hasWarning && ($metrics['completion_rate'] ?? 0) >= 80 && ($metrics['average_wait_minutes'] ?? PHP_INT_MAX) <= 15) {
            $insights[] = new Insight('running_well', Insight::SEVERITY_POSITIVE, [
                'rate' => $metrics['completion_rate'],
                'minutes' => $metrics['average_wait_minutes'],
            ]);
        }

        $order = [Insight::SEVERITY_WARNING => 0, Insight::SEVERITY_SUGGESTION => 1, Insight::SEVERITY_POSITIVE => 2];
        usort($insights, fn (Insight $a, Insight $b): int => $order[$a->severity] <=> $order[$b->severity]);

        return $insights;
    }

    /**
     * Queues whose real service time differs clearly from their setting, which
     * makes the wait shown to customers wrong until history takes over.
     *
     * @param  Collection<int, object>  $tickets
     * @return list<Insight>
     */
    private function serviceTimeDrift(Collection $tickets): array
    {
        $byQueue = $tickets
            ->where('status', TicketStatus::Completed->value)
            ->groupBy('queue_id')
            ->map(fn (Collection $completed): Collection => $this->serviceMinutes($completed)->sort()->values())
            ->filter(fn (Collection $minutes): bool => $minutes->count() >= 10);
        if ($byQueue->isEmpty()) {
            return [];
        }

        $queues = Queue::query()->with('service')->whereIn('id', $byQueue->keys())->get()->keyBy('id');
        $insights = [];
        foreach ($byQueue as $queueId => $minutes) {
            $queue = $queues->get($queueId);
            if ($queue === null) {
                continue;
            }
            $actual = (int) round($minutes->median());
            $configured = $queue->configuredServiceMinutes();
            if (abs($actual - $configured) >= 2 && abs($actual - $configured) / max(1, $configured) >= 0.3) {
                $insights[] = new Insight('service_time_drift', Insight::SEVERITY_SUGGESTION, [
                    'queue' => $queue->name,
                    'actual' => max(1, $actual),
                    'configured' => $configured,
                ]);
            }
        }

        return $insights;
    }

    private static function hour(int $hour): string
    {
        return sprintf('%02d:00', $hour);
    }
}
