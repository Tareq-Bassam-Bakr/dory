<?php

namespace App\Services\Business;

use App\Enums\AuditAction;
use App\Enums\BusinessStatus;
use App\Enums\InvitationStatus;
use App\Exceptions\IdempotencyKeyReusedException;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\SubscriptionService;
use App\Services\Catalog\ServiceCatalogService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Business lifecycle: creation (with owner, optional first branch and
 * services), profile updates, status changes and deletion.
 *
 * Authorization happens before these methods are called (BusinessPolicy).
 */
final class BusinessService
{
    public function __construct(
        private readonly BusinessMembershipService $memberships,
        private readonly BranchService $branches,
        private readonly ServiceCatalogService $services,
        private readonly AuditLogger $audit,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Creates the business, its owner membership and, when given, the first
     * branch and its services, all in one transaction: either everything is
     * saved or nothing is.
     *
     * With an idempotency key, retrying the same request returns the
     * business created the first time instead of a duplicate.
     *
     * @param  array<string, mixed>  $attributes  Validated business fields.
     * @param  array<string, mixed>|null  $branch  Validated branch fields.
     * @param  list<array<string, mixed>>  $services  Validated service fields for the branch.
     * @return array{0: Business, 1: bool} The business, and whether this call created it.
     */
    public function create(
        User $owner,
        BusinessCategory $category,
        array $attributes,
        ?array $branch = null,
        array $services = [],
        ?string $idempotencyKey = null,
    ): array {
        $existing = $this->findByIdempotencyKey($owner, $idempotencyKey);
        if ($existing !== null) {
            return [$existing, false];
        }

        try {
            $business = DB::transaction(function () use ($owner, $category, $attributes, $branch, $services, $idempotencyKey): Business {
                $business = new Business($attributes);
                $business->slug = BusinessMembershipService::uniqueSlug($business->name);
                $business->idempotency_key = $idempotencyKey;
                $business->category()->associate($category);

                $this->memberships->persistWithOwner($business, $owner);
                // The trial or free plan (docs/SUBSCRIPTIONS.md), before the
                // first branch, which counts against it.
                $this->subscriptions->start($business, $owner);

                if ($branch !== null) {
                    $firstBranch = $this->branches->create($business, $branch);

                    foreach ($services as $service) {
                        $this->services->create($firstBranch, $service);
                    }
                }

                $this->audit->record($business, $owner, AuditAction::BusinessCreated, $business, [
                    'category' => $category->slug,
                    'with_branch' => $branch !== null,
                    'services' => count($services),
                ]);

                return $business;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent retry with the same key won the race.
            $existing = $this->findByIdempotencyKey($owner, $idempotencyKey);
            if ($existing !== null) {
                return [$existing, false];
            }

            throw $e;
        }

        return [$business, true];
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated profile fields (no status).
     */
    public function update(
        Business $business,
        User $actor,
        array $attributes,
        ?BusinessCategory $category = null,
        ?BusinessStatus $status = null,
    ): Business {
        return DB::transaction(function () use ($business, $actor, $attributes, $category, $status): Business {
            $business->fill($attributes);

            if ($category !== null) {
                $business->category()->associate($category);
            }

            $previousStatus = $business->status;
            if ($status !== null) {
                $business->status = $status;
            }

            $changed = array_keys($business->getDirty());
            $business->saveOrFail();

            $profileFields = array_values(array_diff($changed, ['status', 'updated_at']));
            if ($profileFields !== []) {
                $this->audit->record($business, $actor, AuditAction::BusinessUpdated, $business, [
                    'fields' => $profileFields,
                ]);
            }

            if ($status !== null && $status !== $previousStatus) {
                $this->audit->record($business, $actor, AuditAction::BusinessStatusChanged, $business, [
                    'from' => $previousStatus->value,
                    'to' => $status->value,
                ]);
            }

            return $business;
        });
    }

    /**
     * Archives the business: status "archived", pending invitations revoked,
     * then a soft delete. Members lose access immediately; rows are kept.
     */
    public function delete(Business $business, User $actor): void
    {
        DB::transaction(function () use ($business, $actor): void {
            $business->invitations()
                ->where('status', InvitationStatus::Pending->value)
                ->update([
                    'status' => InvitationStatus::Revoked->value,
                    'pending_key' => null,
                    'responded_at' => now(),
                ]);

            $business->status = BusinessStatus::Archived;
            $business->saveOrFail();

            $this->audit->record($business, $actor, AuditAction::BusinessDeleted, $business);

            $business->deleteOrFail();
        });
    }

    private function findByIdempotencyKey(User $owner, ?string $key): ?Business
    {
        if ($key === null) {
            return null;
        }

        $business = Business::withTrashed()
            ->where('created_by', $owner->getKey())
            ->where('idempotency_key', $key)
            ->first();

        if ($business?->trashed()) {
            throw new IdempotencyKeyReusedException;
        }

        return $business;
    }
}
