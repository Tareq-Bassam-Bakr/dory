<?php

namespace App\Models;

use App\Enums\NotificationKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one user wants to be told (docs/NOTIFICATION_CENTER.md). Users without
 * a row get the defaults: everything on.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $ticket_updates
 * @property bool $almost_turn
 * @property bool $queue_updates
 * @property bool $staff_new_tickets
 * @property bool $staff_cancellations
 * @property bool $push_enabled
 */
#[Fillable(['ticket_updates', 'almost_turn', 'queue_updates', 'staff_new_tickets', 'staff_cancellations', 'push_enabled'])]
class NotificationPreference extends Model
{
    public const FLAGS = [
        'ticket_updates',
        'almost_turn',
        'queue_updates',
        'staff_new_tickets',
        'staff_cancellations',
        'push_enabled',
    ];

    /**
     * @var array<string, bool>
     */
    protected $attributes = [
        'ticket_updates' => true,
        'almost_turn' => true,
        'queue_updates' => true,
        'staff_new_tickets' => true,
        'staff_cancellations' => true,
        'push_enabled' => true,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_fill_keys(self::FLAGS, 'boolean');
    }

    /**
     * The saved preferences, or the defaults (not saved) when there are none.
     */
    public static function for(User $user): self
    {
        return self::query()->where('user_id', $user->getKey())->first()
            ?? (new self)->forceFill(['user_id' => $user->getKey()]);
    }

    public function allows(NotificationKind $kind): bool
    {
        return (bool) $this->getAttribute($kind->preference());
    }

    /**
     * @return array<string, bool>
     */
    public function flags(): array
    {
        $flags = [];
        foreach (self::FLAGS as $flag) {
            $flags[$flag] = (bool) $this->getAttribute($flag);
        }

        return $flags;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
