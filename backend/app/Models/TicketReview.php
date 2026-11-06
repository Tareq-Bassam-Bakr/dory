<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's rating (1 to 5) of a completed ticket. Created only by
 * TicketReviewService; nothing here is fillable.
 *
 * @property int $id
 * @property string $public_id
 * @property int $queue_ticket_id
 * @property int|null $user_id
 * @property int $business_id
 * @property int $rating
 */
class TicketReview extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'queue_ticket_id', 'user_id', 'business_id'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    /**
     * @return BelongsTo<QueueTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
