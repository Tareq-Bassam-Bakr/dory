<?php

namespace App\Services\Queues;

use App\Enums\MembershipStatus;
use App\Enums\TicketStatus;
use App\Exceptions\QueueException;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\QueueTicket;
use App\Models\TicketReview;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A customer rates a ticket they completed. The review and the business's
 * rating summary change in one transaction, under a lock on the business
 * row, so concurrent reviews cannot lose a count. The queue engine's locks
 * are never involved: ratings do not touch sessions or tickets.
 */
final class TicketReviewService
{
    public function submit(QueueTicket $ticket, User $customer, int $rating): TicketReview
    {
        $businessId = $ticket->queue->branch->business_id;

        if (! $this->mayReview($ticket, $customer, $businessId)) {
            throw QueueException::ticketNotReviewable();
        }

        try {
            return DB::transaction(function () use ($ticket, $customer, $rating, $businessId): TicketReview {
                $business = Business::query()->whereKey($businessId)->lockForUpdate()->firstOrFail();

                if (TicketReview::query()->where('queue_ticket_id', $ticket->getKey())->exists()) {
                    throw QueueException::alreadyReviewed();
                }

                $review = new TicketReview;
                $review->queue_ticket_id = $ticket->getKey();
                $review->user_id = $customer->getKey();
                $review->business_id = $businessId;
                $review->rating = $rating;
                $review->saveOrFail();

                $this->refreshSummary($business);

                return $review;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw QueueException::alreadyReviewed();
        }
    }

    /**
     * Only the holder of a completed ticket, and not the business's own team.
     */
    private function mayReview(QueueTicket $ticket, User $customer, int $businessId): bool
    {
        return $ticket->status === TicketStatus::Completed
            && $ticket->user_id === $customer->getKey()
            && ! BusinessMember::query()
                ->where('business_id', $businessId)
                ->where('user_id', $customer->getKey())
                ->where('status', MembershipStatus::Active->value)
                ->exists();
    }

    private function refreshSummary(Business $business): void
    {
        $summary = TicketReview::query()
            ->where('business_id', $business->getKey())
            ->selectRaw('count(*) as total, avg(rating) as average')
            ->toBase()
            ->first();

        $business->rating_count = (int) $summary->total;
        $business->rating_average = round((float) $summary->average, 2);
        // A rating is not an edit of the business profile.
        $business->timestamps = false;
        $business->saveOrFail();
    }
}
