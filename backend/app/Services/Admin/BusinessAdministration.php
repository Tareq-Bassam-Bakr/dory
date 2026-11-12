<?php

namespace App\Services\Admin;

use App\Enums\AuditAction;
use App\Enums\BusinessStatus;
use App\Enums\PlatformAuditAction;
use App\Exceptions\AdminActionException;
use App\Models\Business;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Platform\PlatformAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Platform review of businesses (docs/ADMIN_DASHBOARD.md, "Businesses").
 * Authorization happens before these methods are called; each change is
 * recorded in the platform audit log and in the business's own history.
 *
 * - Approve: marks the business as reviewed by DORY. It changes nothing for
 *   the business unless approval is required for discovery (setting).
 * - Suspend: the existing platform suspension. Members cannot change the
 *   business or run its queues, and it disappears from discovery. Its
 *   records stay.
 * - Reactivate: restores the status it had before (active or inactive).
 */
final class BusinessAdministration
{
    public function __construct(
        private readonly PlatformAuditLogger $audit,
        private readonly AuditLogger $businessAudit,
    ) {}

    public function approve(Business $business, User $admin): Business
    {
        return DB::transaction(function () use ($business, $admin): Business {
            $locked = $this->lock($business);
            if ($locked->approved_at !== null) {
                throw AdminActionException::notAllowed('already_approved');
            }
            $locked->forceFill(['approved_at' => now(), 'approved_by' => $admin->getKey()])->saveOrFail();
            $this->audit->record(PlatformAuditAction::BusinessApproved, $admin, $locked);

            return $locked;
        });
    }

    public function suspend(Business $business, User $admin, string $reason): Business
    {
        return DB::transaction(function () use ($business, $admin, $reason): Business {
            $locked = $this->lock($business);
            if ($locked->status === BusinessStatus::Suspended) {
                throw AdminActionException::notAllowed('already_suspended');
            }
            if (! in_array($locked->status, [BusinessStatus::Active, BusinessStatus::Inactive], true) || $locked->trashed()) {
                throw AdminActionException::notAllowed('not_suspendable');
            }
            $previous = $locked->status;
            $locked->forceFill([
                'status' => BusinessStatus::Suspended,
                'status_before_suspension' => $previous->value,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
            ])->saveOrFail();

            $this->audit->record(PlatformAuditAction::BusinessSuspended, $admin, $locked, [
                'reason' => $reason,
                'previous_status' => $previous->value,
            ]);
            $this->businessAudit->record($locked, $admin, AuditAction::BusinessStatusChanged, $locked, [
                'from' => $previous->value,
                'to' => BusinessStatus::Suspended->value,
                'by' => 'platform',
            ]);

            return $locked;
        });
    }

    public function reactivate(Business $business, User $admin): Business
    {
        return DB::transaction(function () use ($business, $admin): Business {
            $locked = $this->lock($business);
            if ($locked->status !== BusinessStatus::Suspended) {
                throw AdminActionException::notAllowed('not_suspended');
            }
            $restored = BusinessStatus::tryFrom((string) $locked->status_before_suspension);
            $restored = in_array($restored, [BusinessStatus::Active, BusinessStatus::Inactive], true) ? $restored : BusinessStatus::Active;
            $locked->forceFill([
                'status' => $restored,
                'status_before_suspension' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->saveOrFail();

            $this->audit->record(PlatformAuditAction::BusinessReactivated, $admin, $locked, ['status' => $restored->value]);
            $this->businessAudit->record($locked, $admin, AuditAction::BusinessStatusChanged, $locked, [
                'from' => BusinessStatus::Suspended->value,
                'to' => $restored->value,
                'by' => 'platform',
            ]);

            return $locked;
        });
    }

    private function lock(Business $business): Business
    {
        return Business::query()->withTrashed()->whereKey($business->getKey())->lockForUpdate()->firstOrFail();
    }
}
