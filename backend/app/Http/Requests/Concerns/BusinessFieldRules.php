<?php

namespace App\Http\Requests\Concerns;

use App\Enums\BranchStatus;
use App\Enums\ServiceStatus;
use App\Models\BusinessCategory;
use App\Support\ContactInput;
use Illuminate\Validation\Rule;

/**
 * Validation rules for business, branch and service fields, shared by the
 * standalone endpoints and the nested onboarding payload of POST /businesses.
 *
 * Identity and ownership fields (ids, slugs, parent ids, owners, storage
 * paths) are prohibited: sending them fails validation instead of being
 * silently ignored, so tenant-hopping attempts are explicit and testable.
 */
trait BusinessFieldRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function businessProfileRules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'min:2', 'max:150'],
            'category' => [
                ...$required,
                'string',
                Rule::exists(BusinessCategory::class, 'slug')->where('is_active', true),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', ContactInput::PHONE_RULE],
            'website' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:255'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function protectedBusinessFieldRules(): array
    {
        return self::prohibit([
            'id', 'public_id', 'slug', 'created_by', 'owner', 'owner_id', 'business_id',
            'category_id', 'logo_path', 'cover_path', 'idempotency_key', 'deleted_at',
        ]);
    }

    /**
     * @param  string  $prefix  e.g. "branch." for the nested onboarding branch.
     * @param  list<string>  $conditions  Rules prepended to every field, e.g. exclude_without.
     * @return array<string, list<mixed>>
     */
    protected function branchRules(string $prefix = '', bool $partial = false, array $conditions = []): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $optional = ['sometimes', 'nullable'];

        return [
            $prefix.'name' => [...$conditions, ...$required, 'string', 'min:2', 'max:150'],
            $prefix.'address' => [...$conditions, ...$optional, 'string', 'max:255'],
            $prefix.'country' => [...$conditions, ...$optional, 'string', ContactInput::COUNTRY_RULE],
            $prefix.'city' => [...$conditions, ...$optional, 'string', 'max:120'],
            $prefix.'region' => [...$conditions, ...$optional, 'string', 'max:120'],
            // WGS 84 decimal degrees (OpenStreetMap). Both or neither: no
            // "sometimes" here, or a missing partner would skip required_with.
            $prefix.'latitude' => [...$conditions, 'nullable', 'numeric', 'between:-90,90', 'required_with:'.$prefix.'longitude'],
            $prefix.'longitude' => [...$conditions, 'nullable', 'numeric', 'between:-180,180', 'required_with:'.$prefix.'latitude'],
            $prefix.'phone' => [...$conditions, ...$optional, 'string', ContactInput::PHONE_RULE],
            $prefix.'email' => [...$conditions, ...$optional, 'string', 'email:rfc', 'max:255'],
            $prefix.'timezone' => [...$conditions, 'sometimes', 'required', 'string', 'timezone:all'],
            $prefix.'status' => [...$conditions, 'sometimes', 'required', Rule::enum(BranchStatus::class)],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function protectedBranchFieldRules(string $prefix = ''): array
    {
        return self::prohibit(['id', 'public_id', 'business_id', 'deleted_at'], $prefix);
    }

    /**
     * @param  list<mixed>  $nameRules  Extra rules for the name (uniqueness).
     * @return array<string, list<mixed>>
     */
    protected function serviceRules(string $prefix = '', bool $partial = false, array $nameRules = []): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            $prefix.'name' => [...$required, 'string', 'min:2', 'max:150', ...$nameRules],
            $prefix.'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            $prefix.'estimated_duration_minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:1440'],
            $prefix.'display_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:65535'],
            $prefix.'status' => ['sometimes', 'required', Rule::enum(ServiceStatus::class)],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function protectedServiceFieldRules(string $prefix = ''): array
    {
        return self::prohibit(['id', 'public_id', 'branch_id', 'business_id', 'deleted_at'], $prefix);
    }

    /**
     * Fields a branch payload may contain.
     *
     * @return list<string>
     */
    protected static function branchKeys(): array
    {
        return [
            'name', 'address', 'country', 'city', 'region', 'latitude', 'longitude',
            'phone', 'email', 'timezone', 'status',
        ];
    }

    /**
     * Fields a service payload may contain.
     *
     * @return list<string>
     */
    protected static function serviceKeys(): array
    {
        return ['name', 'description', 'estimated_duration_minutes', 'display_order', 'status'];
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, list<string>>
     */
    private static function prohibit(array $fields, string $prefix = ''): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules[$prefix.$field] = ['prohibited'];
        }

        return $rules;
    }
}
