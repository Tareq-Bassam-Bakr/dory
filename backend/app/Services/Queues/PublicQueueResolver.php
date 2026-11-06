<?php

namespace App\Services\Queues;

use App\Exceptions\QueueException;
use App\Models\Queue;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Route resolution for customers and guests, who are not business members:
 *
 * - {joinableQueue}:  a live queue by public id (signed-in customers).
 * - {publicQueueToken}: a live queue by its public link token (hashed lookup).
 * - {myTicket}:       a ticket owned by the signed-in user.
 * - {publicTicket}:   a guest ticket by public id; the access token is
 *                     verified separately (EnsureTicketAccessToken).
 *
 * The queue's business and branch must still exist. Every miss is the same
 * 404, so lookups reveal nothing.
 */
final class PublicQueueResolver
{
    public function joinableQueue(string $publicId): Queue
    {
        return $this->liveQueues()->where('public_id', $publicId)->first()
            ?? throw QueueException::notFound('queue');
    }

    public function byPublicToken(string $token): Queue
    {
        return $this->liveQueues()->where('public_token_hash', hash('sha256', $token))->first()
            ?? throw QueueException::notFound('queue');
    }

    public function myTicket(?User $user, string $publicId): QueueTicket
    {
        return $user === null ? throw QueueException::notFound('ticket') : (QueueTicket::query()
            ->where('public_id', $publicId)
            ->where('user_id', $user->getKey())
            ->with(['queue.branch.business', 'queue.service', 'session'])
            ->first() ?? throw QueueException::notFound('ticket'));
    }

    public function publicTicket(string $publicId): QueueTicket
    {
        return QueueTicket::query()
            ->where('public_id', $publicId)
            ->whereNotNull('public_access_token_hash')
            ->with(['queue.branch.business', 'queue.service', 'session'])
            ->first()
            ?? throw QueueException::notFound('ticket');
    }

    /**
     * @return Builder<Queue>
     */
    private function liveQueues(): Builder
    {
        return Queue::query()
            ->whereHas('branch.business')
            ->with(['branch.business.category', 'service']);
    }
}
