<?php

namespace App\Services\Platform;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The few settings platform admins may change while DORY runs
 * (docs/ADMIN_DASHBOARD.md, "Settings"). Their defaults come from config/
 * and .env; a value stored here overrides its default for every request.
 * Changing a value back to its default removes the override.
 *
 * Deliberately not here: the payment provider, the live-payments switch,
 * credentials, rate limits. Those change on the server only.
 */
final class PlatformSettings
{
    private const CACHE_KEY = 'platform_settings.v1';

    /**
     * Form field => config key and type.
     *
     * @var array<string, array{key: string, type: string, min?: int, max?: int}>
     */
    public const FIELDS = [
        'billing_enabled' => ['key' => 'billing.enabled', 'type' => 'bool'],
        'trial_plan' => ['key' => 'billing.trial.plan', 'type' => 'plan'],
        'trial_days' => ['key' => 'billing.trial.days', 'type' => 'int', 'min' => 0, 'max' => 90],
        'trial_once_per_owner' => ['key' => 'billing.trial.once_per_owner', 'type' => 'bool'],
        'grace_days' => ['key' => 'billing.grace_days', 'type' => 'int', 'min' => 0, 'max' => 60],
        'currencies' => ['key' => 'billing.currencies', 'type' => 'currencies'],
        'default_currency' => ['key' => 'billing.currency', 'type' => 'currency'],
        'four_eyes' => ['key' => 'billing.manual_payments.four_eyes', 'type' => 'bool'],
        'require_business_approval' => ['key' => 'dory.businesses.require_approval', 'type' => 'bool'],
    ];

    /** @var array<string, mixed> config key => value before overrides */
    private array $defaults = [];

    public function __construct(private readonly PlatformAuditLogger $audit) {}

    /**
     * Applies the stored values over the defaults (AppServiceProvider::boot).
     */
    public function apply(): void
    {
        $this->captureDefaults();
        $keys = array_column(self::FIELDS, 'key');
        foreach ($this->stored() as $key => $value) {
            if (in_array($key, $keys, true)) {
                config([$key => $value]);
            }
        }
    }

    /**
     * Every field with its value now, its default and who changed it.
     *
     * @return array<string, array{value: mixed, default: mixed, overridden: bool, updated_at: ?string, updated_by: ?string}>
     */
    public function current(): array
    {
        $this->captureDefaults();
        $rows = $this->tableExists()
            ? PlatformSetting::query()->with('updatedBy:id,name')->get()->keyBy('key')
            : collect();
        $current = [];
        foreach (self::FIELDS as $field => $definition) {
            $row = $rows->get($definition['key']);
            $current[$field] = [
                'value' => config($definition['key']),
                'default' => $this->defaults[$definition['key']],
                'overridden' => $row !== null,
                'updated_at' => $row?->updated_at?->toIso8601String(),
                'updated_by' => $row?->updatedBy?->name,
            ];
        }

        return $current;
    }

    /**
     * Validates and stores the given fields; returns what changed.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function update(array $input, User $admin): array
    {
        $this->captureDefaults();
        $values = $this->normalize($input);
        $validated = Validator::make($values, $this->rules($values))->validate();

        $changes = [];
        DB::transaction(function () use ($validated, $admin, &$changes): void {
            foreach (self::FIELDS as $field => $definition) {
                if (! array_key_exists($field, $validated)) {
                    continue;
                }
                $key = $definition['key'];
                $new = $this->cast($validated[$field], $definition['type']);
                $old = config($key);
                if ($new === $old) {
                    continue;
                }
                if ($new === $this->defaults[$key]) {
                    PlatformSetting::query()->whereKey($key)->delete();
                } else {
                    PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $new, 'updated_by' => $admin->getKey()]);
                }
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
            if ($changes !== []) {
                $this->audit->record(PlatformAuditAction::SettingsUpdated, $admin, null, ['changes' => $changes]);
            }
        });

        Cache::forget(self::CACHE_KEY);
        foreach ($changes as $field => $change) {
            config([self::FIELDS[$field]['key'] => $change['to']]);
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        $values = [];
        foreach (self::FIELDS as $field => $definition) {
            if (! array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field];
            $values[$field] = match ($definition['type']) {
                'currencies' => array_values(array_unique(array_filter(array_map(
                    fn ($code): string => strtoupper(trim((string) $code)),
                    is_array($value) ? $value : (preg_split('/[\s,]+/', (string) $value) ?: []),
                )))),
                'currency' => is_string($value) ? strtoupper(trim($value)) : $value,
                'plan' => is_string($value) ? trim($value) : $value,
                default => $value,
            };
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function rules(array $values): array
    {
        $currencies = $values['currencies'] ?? config('billing.currencies');
        $rules = [];
        foreach (self::FIELDS as $field => $definition) {
            $rules[$field] = match ($definition['type']) {
                'bool' => ['sometimes', 'boolean'],
                'int' => ['sometimes', 'integer', 'min:'.$definition['min'], 'max:'.$definition['max']],
                'plan' => ['sometimes', 'string', Rule::exists('subscription_plans', 'code')->where('is_free', false)],
                'currencies' => ['sometimes', 'array', 'min:1', 'max:10'],
                'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/', Rule::in((array) $currencies)],
            };
        }
        $rules['currencies.*'] = ['string', 'distinct', 'regex:/^[A-Z]{3}$/'];

        return $rules;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            'currencies' => array_values((array) $value),
            default => (string) $value,
        };
    }

    private function captureDefaults(): void
    {
        if ($this->defaults !== []) {
            return;
        }
        foreach (self::FIELDS as $definition) {
            $this->defaults[$definition['key']] = config($definition['key']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->tableExists()
                ? PlatformSetting::query()->pluck('value', 'key')->all()
                : []);
        } catch (Throwable) {
            // Before the first migration (no cache or settings table yet).
            return [];
        }
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
