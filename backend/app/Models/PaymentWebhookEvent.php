<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A provider notification, kept once per provider event id so a repeated
 * delivery is recognised and ignored.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $status
 * @property ?array<string, mixed> $summary
 * @property ?string $error
 * @property Carbon $received_at
 * @property ?Carbon $processed_at
 */
class PaymentWebhookEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
