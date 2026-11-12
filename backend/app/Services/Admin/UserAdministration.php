<?php

namespace App\Services\Admin;

use App\Enums\PlatformAuditAction;
use App\Enums\UserStatus;
use App\Exceptions\AdminActionException;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Account status by platform admins (docs/ADMIN_DASHBOARD.md, "Users").
 *
 * Suspending signs the account out everywhere (its API tokens are revoked)
 * and the API refuses it from then on. Platform admin accounts, and your
 * own, are changed from the command line only (php artisan dory:admin).
 */
final class UserAdministration
{
    public function __construct(private readonly PlatformAuditLogger $audit) {}

    public function suspend(User $target, User $admin, string $reason): User
    {
        $this->ensureManageable($target, $admin);

        return DB::transaction(function () use ($target, $admin, $reason): User {
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === UserStatus::Suspended) {
                throw AdminActionException::notAllowed('already_suspended');
            }
            $previous = $locked->status;
            $locked->forceFill(['status' => UserStatus::Suspended])->saveOrFail();
            $revoked = $locked->tokens()->delete();

            $this->audit->record(PlatformAuditAction::UserSuspended, $admin, $locked, [
                'reason' => $reason,
                'previous_status' => $previous->value,
                'tokens_revoked' => $revoked,
            ]);

            return $locked;
        });
    }

    public function reactivate(User $target, User $admin): User
    {
        $this->ensureManageable($target, $admin);

        return DB::transaction(function () use ($target, $admin): User {
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== UserStatus::Suspended) {
                throw AdminActionException::notAllowed('not_suspended');
            }
            $locked->forceFill(['status' => UserStatus::Active])->saveOrFail();
            $this->audit->record(PlatformAuditAction::UserReactivated, $admin, $locked);

            return $locked;
        });
    }

    private function ensureManageable(User $target, User $admin): void
    {
        if ($target->is($admin)) {
            throw AdminActionException::forbidden('own_account');
        }
        if ($target->isPlatformAdmin()) {
            throw AdminActionException::forbidden('admin_account');
        }
    }
}
