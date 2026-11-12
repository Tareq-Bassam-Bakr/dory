<?php

namespace App\Services\Platform;

use App\Enums\PlatformAuditAction;
use App\Models\Business;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records sensitive platform operations (docs/ADMIN_DASHBOARD.md, "Audit
 * log"): who, what, on which record, from where. Never passwords, tokens or
 * card data.
 */
final class PlatformAuditLogger
{
    public const CONSOLE = 'console';

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        PlatformAuditAction $action,
        ?User $actor,
        ?Model $subject = null,
        array $properties = [],
        ?Business $business = null,
        ?string $actorLabel = null,
    ): PlatformAuditLog {
        $request = $actorLabel === self::CONSOLE ? null : $this->request();
        $business ??= $subject instanceof Business ? $subject : null;

        $entry = new PlatformAuditLog;
        $entry->forceFill([
            'actor_id' => $actor?->getKey(),
            'actor_label' => $actorLabel,
            'action' => $action,
            'business_id' => $business?->getKey(),
            'subject_type' => $subject === null ? null : Str::snake(class_basename($subject)),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ])->saveOrFail();

        return $entry;
    }

    private function request(): ?Request
    {
        return app()->bound('request') ? app('request') : null;
    }
}
