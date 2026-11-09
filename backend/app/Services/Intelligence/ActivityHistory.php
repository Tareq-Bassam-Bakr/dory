<?php

namespace App\Services\Intelligence;

use App\Exceptions\BranchNotFoundException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads the ticket history behind peak times and insights
 * (docs/AI_ARCHITECTURE.md).
 *
 * Tenant isolation: only branches of this business that the membership
 * reaches (all of them for owners and managers of every branch, the selected
 * ones otherwise). A branch outside that set, or of another business, is
 * reported as not found, like everywhere else in the API.
 *
 * Only what the analysis needs is read: queue, status, times and cancel
 * reason. No names, phone numbers or user ids.
 */
final class ActivityHistory
{
    /**
     * Branches this membership may analyse, optionally narrowed to one.
     *
     * @return Collection<int, Branch>
     */
    public function branches(Business $business, BusinessMember $membership, ?string $branchPublicId): Collection
    {
        $branches = Branch::query()
            ->where('business_id', $business->getKey())
            ->when(! $membership->hasAllBranchAccess(), fn ($query) => $query
                ->whereIn('id', $membership->branches()->pluck('branches.id')))
            ->when($branchPublicId !== null, fn ($query) => $query->where('public_id', $branchPublicId))
            ->orderBy('id')
            ->get();

        if ($branchPublicId !== null && $branches->isEmpty()) {
            throw new BranchNotFoundException;
        }

        return $branches;
    }

    /**
     * Tickets that joined since $since, newest first, at most max_rows.
     * "joined_local" is the join time in the branch's own time zone.
     *
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, object{queue_id: int, status: string, joined_at: Carbon, joined_local: Carbon, called_at: ?Carbon, started_at: ?Carbon, completed_at: ?Carbon, cancel_reason: ?string}>
     */
    public function tickets(Collection $branches, Carbon $since): Collection
    {
        if ($branches->isEmpty()) {
            return collect();
        }

        $zones = $branches->mapWithKeys(fn (Branch $branch): array => [$branch->getKey() => $branch->timezone ?: 'UTC']);
        $parse = fn (?string $value): ?Carbon => $value === null ? null : Carbon::parse($value, 'UTC');

        return DB::table('queue_tickets')
            ->join('queues', 'queues.id', '=', 'queue_tickets.queue_id')
            ->whereIn('queues.branch_id', $zones->keys())
            ->where('queue_tickets.joined_at', '>=', $since)
            ->orderByDesc('queue_tickets.joined_at')
            ->limit(max(1, (int) config('dory.intelligence.max_rows')))
            ->get([
                'queue_tickets.queue_id',
                'queues.branch_id',
                'queue_tickets.status',
                'queue_tickets.joined_at',
                'queue_tickets.called_at',
                'queue_tickets.started_at',
                'queue_tickets.completed_at',
                'queue_tickets.cancel_reason',
            ])
            ->map(function (object $row) use ($zones, $parse): object {
                $joined = $parse($row->joined_at);

                return (object) [
                    'queue_id' => (int) $row->queue_id,
                    'status' => (string) $row->status,
                    'joined_at' => $joined,
                    'joined_local' => $joined->copy()->setTimezone($zones[$row->branch_id] ?? 'UTC'),
                    'called_at' => $parse($row->called_at),
                    'started_at' => $parse($row->started_at),
                    'completed_at' => $parse($row->completed_at),
                    'cancel_reason' => $row->cancel_reason,
                ];
            });
    }
}
