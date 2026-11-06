<?php

namespace App\Services\Queues;

use App\Contracts\WaitTimeEstimator;
use App\Enums\JoinSource;
use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Exceptions\QueueException;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Joining a queue (docs/GUEST_JOINING.md, docs/CONCURRENCY_STRATEGY.md).
 *
 * One transaction per join:
 *   1. lock the queue's active session row (SELECT … FOR UPDATE): joins to
 *      the same queue run one at a time, so capacity, duplicate and
 *      idempotency checks see every committed ticket;
 *   2. replay an identical earlier request (Idempotency-Key);
 *   3. check the session accepts joins, the user has no active ticket in it
 *      (also a unique index) and capacity (waiting tickets) is not reached;
 *   4. take the next number from the daily counter (atomic upsert + row
 *      lock; never MAX()+1) and insert the ticket (unique per queue, day
 *      and sequence number);
 *   5. record the "joined" event.
 *
 * Lock order is always session → counter (→ ticket), which keeps
 * concurrent joins and staff actions free of lock-order deadlocks.
 */
final class TicketJoinService
{
    public const ACCESS_TOKEN_LENGTH = 48;

    public function __construct(
        private readonly TicketNumberAllocator $numbers,
        private readonly QueueEventRecorder $events,
        private readonly WaitTimeEstimator $estimator,
        private readonly TicketPositions $positions,
    ) {}

    /**
     * A signed-in customer joins (the DORY app).
     */
    public function joinAsCustomer(Queue $queue, User $user, ?string $idempotencyKey = null): JoinResult
    {
        return $this->join(
            $queue,
            $user,
            [],
            JoinSource::App,
            $idempotencyKey === null ? null : self::idempotencyHash('user:'.$user->getKey(), $idempotencyKey),
        );
    }

    /**
     * A guest joins through the queue's public link or QR code. The result
     * carries the ticket access token in plaintext, once.
     */
    public function joinAsGuest(Queue $queue, ?string $name, ?string $phone, ?string $idempotencyKey = null): JoinResult
    {
        if (! $queue->allow_guest_join) {
            throw QueueException::guestJoinDisabled();
        }

        return $this->join(
            $queue,
            null,
            ['guest_name' => $name, 'guest_phone' => $phone],
            JoinSource::PublicLink,
            $idempotencyKey === null ? null : self::idempotencyHash('guest', $idempotencyKey),
        );
    }

    /**
     * @param  array{guest_name?: string|null, guest_phone?: string|null}  $guest
     */
    private function join(Queue $queue, ?User $user, array $guest, JoinSource $source, ?string $keyHash): JoinResult
    {
        if (! $queue->isActive()) {
            throw QueueException::queueInactive();
        }

        try {
            return DB::transaction(function () use ($queue, $user, $guest, $source, $keyHash): JoinResult {
                // 1. Serialize joins of this queue (must be the first statement).
                $session = QueueSession::query()
                    ->where('queue_id', $queue->getKey())
                    ->whereNotNull('active_key')
                    ->lockForUpdate()
                    ->first();

                // 2. Retry of an earlier identical request.
                $existing = $this->findByKey($queue, $keyHash);
                if ($existing !== null) {
                    return $this->replay($existing, $queue, $user === null);
                }

                // 3. Can this person join now?
                if ($session === null) {
                    throw QueueException::queueClosed();
                }
                if (! $session->acceptsJoins()) {
                    throw QueueException::queuePaused();
                }
                if ($user !== null && $this->hasActiveTicket($session, $user)) {
                    throw QueueException::duplicateActiveTicket();
                }
                $waiting = $this->positions->waitingCount($session);
                if ($queue->capacity !== null && $waiting >= $queue->capacity) {
                    throw QueueException::queueFull();
                }

                // 4. Number and create the ticket.
                $number = $this->numbers->next($queue, $session->business_date);
                $estimate = $this->estimator->estimate($queue, $waiting);
                $token = $user === null ? Str::random(self::ACCESS_TOKEN_LENGTH) : null;

                $ticket = new QueueTicket;
                $ticket->queue_session_id = $session->getKey();
                $ticket->queue_id = $queue->getKey();
                $ticket->user_id = $user?->getKey();
                $ticket->guest_name = $guest['guest_name'] ?? null;
                $ticket->guest_phone = $guest['guest_phone'] ?? null;
                $ticket->public_access_token_hash = $token === null ? null : QueueTicket::hashAccessToken($token);
                $ticket->idempotency_key_hash = $keyHash;
                $ticket->sequence_number = $number;
                $ticket->ticket_number = TicketNumberAllocator::format($queue->ticket_prefix, $number);
                $ticket->business_date = $session->business_date;
                $ticket->status = TicketStatus::Waiting;
                $ticket->join_source = $source;
                $ticket->joined_at = now();
                $ticket->estimated_wait_seconds = $estimate->seconds;
                $ticket->estimated_at = now();
                $ticket->saveOrFail();
                $ticket->setRelation('queue', $queue)->setRelation('session', $session);

                // 5. History (no names or phone numbers in event metadata).
                $this->events->record($queue, QueueEventType::Joined, $user, $session, $ticket, null, TicketStatus::Waiting, [
                    'source' => $source->value,
                    'ticket_number' => $ticket->ticket_number,
                ]);

                return new JoinResult($ticket, true, $token);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // Only reachable when the session lock was bypassed (e.g. the
            // session was replaced mid-request); the indexes still decide.
            $existing = $this->findByKey($queue, $keyHash);
            if ($existing !== null) {
                return DB::transaction(fn (): JoinResult => $this->replay($existing, $queue, $user === null));
            }
            if ($user !== null) {
                throw QueueException::duplicateActiveTicket();
            }

            throw $e;
        }
    }

    /**
     * The ticket an identical earlier request created. A guest gets a fresh
     * access token (the first response, with the original token, was
     * presumably lost); the previous token stops working.
     */
    private function replay(QueueTicket $ticket, Queue $queue, bool $guest): JoinResult
    {
        $token = null;
        if ($guest) {
            $token = Str::random(self::ACCESS_TOKEN_LENGTH);
            $ticket->public_access_token_hash = QueueTicket::hashAccessToken($token);
            $ticket->saveOrFail();
        }

        $ticket->setRelation('queue', $queue)->load('session');

        return new JoinResult($ticket, false, $token);
    }

    private function findByKey(Queue $queue, ?string $keyHash): ?QueueTicket
    {
        return $keyHash === null ? null : QueueTicket::query()
            ->where('queue_id', $queue->getKey())
            ->where('idempotency_key_hash', $keyHash)
            ->first();
    }

    private function hasActiveTicket(QueueSession $session, User $user): bool
    {
        return QueueTicket::query()
            ->where('queue_session_id', $session->getKey())
            ->where('user_id', $user->getKey())
            ->whereIn('status', TicketStatus::activeValues())
            ->exists();
    }

    /**
     * Keys are scoped to the caller (user id, or "guest") and stored hashed.
     */
    public static function idempotencyHash(string $scope, string $key): string
    {
        return hash('sha256', $scope.'|'.$key);
    }
}
