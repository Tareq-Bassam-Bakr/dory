<?php

namespace App\Http\Controllers\Api\V1\Queues;

use App\Contracts\WaitTimeEstimator;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\QueueSessionResource;
use App\Http\Resources\StaffTicketResource;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Services\Business\CurrentBusinessContext;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The live view of a queue session for the business team. Clients refresh
 * it by polling (no real-time push in Phase 04).
 */
class QueueDashboardController extends Controller
{
    private const NEXT_UP = 5;

    public function __construct(
        private readonly WaitTimeEstimator $estimator,
        private readonly CurrentBusinessContext $context,
    ) {}

    /**
     * GET /queue-sessions/{session}/dashboard
     */
    public function dashboard(Request $request, QueueSession $queueSession): JsonResponse
    {
        $queue = $queueSession->queue;
        Gate::authorize('view', $queue);

        $counts = QueueTicket::query()
            ->where('queue_session_id', $queueSession->getKey())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $countFor = fn (TicketStatus $status): int => (int) ($counts[$status->value] ?? 0);

        $nextUp = $this->query($queueSession, [TicketStatus::Waiting])->limit(self::NEXT_UP)->get();
        $inService = $this->query($queueSession, [TicketStatus::Called, TicketStatus::Serving])->get();
        $nextUp->concat($inService)->each->setRelation('queue', $queue);
        $mine = $inService->firstWhere('served_by', $request->user()->getKey());
        $estimate = $this->estimator->estimate($queue, $countFor(TicketStatus::Waiting));

        return ApiResponse::success([
            'session' => new QueueSessionResource($queueSession),
            'queue' => [
                'id' => $queue->public_id,
                'name' => $queue->name,
                'ticket_prefix' => $queue->ticket_prefix,
                'status' => $queue->status->value,
                'grace_period_seconds' => $queue->grace_period_seconds,
            ],
            'is_current_session' => $queueSession->active_key !== null,
            'counts' => collect(TicketStatus::cases())
                ->mapWithKeys(fn (TicketStatus $status) => [$status->value => $countFor($status)])
                ->put('total', (int) $counts->sum()),
            'next_up' => StaffTicketResource::collection($nextUp),
            'in_service' => StaffTicketResource::collection($inService),
            'my_ticket' => $mine === null ? null : new StaffTicketResource($mine),
            'estimated_wait' => $estimate->toArray() + [
                'seconds_per_customer' => $estimate->secondsPerCustomer,
                'servers' => $estimate->servers,
            ],
            'permissions' => $this->context->abilities()->queuePermissions($queue),
        ]);
    }

    /**
     * GET /queue-sessions/{session}/tickets?status=waiting,called
     */
    public function tickets(Request $request, QueueSession $queueSession): JsonResponse
    {
        Gate::authorize('view', $queueSession->queue);

        $requested = array_values(array_filter(explode(',', (string) $request->query('status', ''))));
        validator(['status' => $requested], ['status.*' => [Rule::enum(TicketStatus::class)]])->validate();
        $statuses = array_map(fn (string $status) => TicketStatus::from($status), $requested);

        $paginator = $this->query($queueSession, $statuses)->paginate(Pagination::perPage($request));
        $paginator->getCollection()->each->setRelation('queue', $queueSession->queue);

        return ApiResponse::paginated($paginator, StaffTicketResource::class);
    }

    /**
     * Active statuses in queue order (priority, then join order); lists
     * that include finished tickets are newest first.
     *
     * @param  list<TicketStatus>  $statuses  Empty for every status.
     * @return Builder<QueueTicket>
     */
    private function query(QueueSession $session, array $statuses): Builder
    {
        $activeOnly = $statuses !== [] && collect($statuses)->every(fn (TicketStatus $status) => $status->isActive());

        $query = QueueTicket::query()
            ->where('queue_session_id', $session->getKey())
            ->when($statuses !== [], fn (Builder $query) => $query->whereIn(
                'status',
                array_map(fn (TicketStatus $status) => $status->value, $statuses),
            ))
            ->with(['user', 'server']);

        return $activeOnly
            ? $query->orderByDesc('priority')->orderBy('id')
            : $query->orderByDesc('id');
    }
}
