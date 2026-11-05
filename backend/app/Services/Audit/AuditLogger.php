<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\Business;
use App\Models\BusinessAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the business audit trail (business_audit_logs).
 *
 * Callers pass only non-sensitive properties: field names, roles, statuses,
 * public ids. Never tokens, passwords or full request payloads.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        Business $business,
        ?User $actor,
        AuditAction $action,
        ?Model $subject = null,
        array $properties = [],
    ): BusinessAuditLog {
        $entry = new BusinessAuditLog([
            'action' => $action,
            'subject_type' => $subject === null ? null : Str::snake(class_basename($subject)),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
        ]);
        $entry->business()->associate($business);
        $entry->actor()->associate($actor);
        $entry->saveOrFail();

        return $entry;
    }
}
