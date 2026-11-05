<?php

namespace Tests\Feature\Api\V1\Businesses;

use App\Enums\AuditAction;
use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessAuditLog;
use App\Models\BusinessCategory;
use App\Models\BusinessMember;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\BusinessCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class BusinessCreationTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private const URI = '/api/v1/businesses';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessCategorySeeder::class);
        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Shifa Clinic',
            'category' => 'healthcare-clinics',
            'description' => 'Family medicine and pediatrics',
            'email' => 'Contact@Shifa.Example',
            'phone' => '+963 11 123 4567',
            'website' => 'https://shifa.example',
            'timezone' => 'Asia/Damascus',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function onboardingPayload(): array
    {
        return $this->payload([
            'branch' => [
                'name' => 'Mazzeh Branch',
                'address' => 'Mazzeh Highway 12',
                'country' => 'sy',
                'city' => 'Damascus',
                'latitude' => 33.5138,
                'longitude' => 36.2765,
                'services' => [
                    ['name' => 'General consultation', 'estimated_duration_minutes' => 20],
                    ['name' => 'Vaccination', 'estimated_duration_minutes' => 10],
                ],
            ],
        ]);
    }

    public function test_an_authenticated_user_creates_a_business_and_becomes_its_owner(): void
    {
        $response = $this->actingAsUser($this->user, 'POST', self::URI, $this->payload());

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.name', 'Shifa Clinic')
            ->assertJsonPath('data.category.slug', 'healthcare-clinics')
            ->assertJsonPath('data.category.name.ar', 'الرعاية الصحية والعيادات')
            ->assertJsonPath('data.email', 'contact@shifa.example')
            ->assertJsonPath('data.phone', '+963111234567')
            ->assertJsonPath('data.timezone', 'Asia/Damascus')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.membership.role', 'owner')
            ->assertJsonPath('data.permissions', [
                'update' => true, 'change_status' => true, 'delete' => true,
                'create_branches' => true, 'view_members' => true, 'manage_members' => true,
            ])
            ->assertJsonPath('data.stats', ['branches' => 0, 'services' => 0, 'members' => 1]);

        $business = Business::query()->sole();
        $membership = BusinessMember::query()->sole();
        $this->assertSame($business->public_id, $response->json('data.id'));
        $this->assertTrue($membership->user->is($this->user));
        $this->assertTrue($membership->business->is($business));
        $this->assertSame(BusinessRole::Owner, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertTrue($business->creator->is($this->user));
    }

    public function test_creation_is_recorded_in_the_audit_log(): void
    {
        $this->actingAsUser($this->user, 'POST', self::URI, $this->payload())->assertCreated();

        $entry = BusinessAuditLog::query()->sole();
        $this->assertSame(AuditAction::BusinessCreated, $entry->action);
        $this->assertTrue($entry->actor->is($this->user));
    }

    public function test_onboarding_creates_the_business_branch_and_services_together(): void
    {
        $response = $this->actingAsUser($this->user, 'POST', self::URI, $this->onboardingPayload());

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.stats.branches', 1)
            ->assertJsonPath('data.stats.services', 2)
            ->assertJsonPath('data.profile', ['percentage' => 100, 'is_complete' => true, 'missing' => []]);

        $branch = Branch::query()->sole();
        $this->assertSame('SY', $branch->country);
        $this->assertSame('Asia/Damascus', $branch->timezone, 'Branches inherit the business timezone.');
        $this->assertEqualsWithDelta(33.5138, $branch->latitude, 0.0000001);
        $this->assertSame(['General consultation', 'Vaccination'], $branch->services()->orderBy('display_order')->pluck('name')->all());
    }

    public function test_profile_completion_lists_what_is_missing(): void
    {
        $response = $this->actingAsUser($this->user, 'POST', self::URI, [
            'name' => 'Quick Cuts',
            'category' => 'beauty-salons',
        ]);

        $response->assertCreated()->assertJsonPath('data.profile', [
            'percentage' => 20,
            'is_complete' => false,
            'missing' => ['description', 'contact', 'branch', 'service'],
        ]);
    }

    public function test_a_failure_part_way_through_creates_nothing(): void
    {
        Service::creating(fn () => throw new RuntimeException('Simulated database failure'));

        $response = $this->actingAsUser($this->user, 'POST', self::URI, $this->onboardingPayload());

        $this->assertApiError($response, 500, 'server_error');
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('business_members', 0);
        $this->assertDatabaseCount('branches', 0);
        $this->assertDatabaseCount('services', 0);
        $this->assertDatabaseCount('business_audit_logs', 0);
    }

    public function test_a_user_can_own_several_businesses(): void
    {
        $this->actingAsUser($this->user, 'POST', self::URI, $this->payload())->assertCreated();
        $this->actingAsUser($this->user, 'POST', self::URI, $this->payload(['name' => 'Shifa Dental']))->assertCreated();

        $this->assertSame(2, BusinessMember::query()->where('user_id', $this->user->id)->where('role', 'owner')->count());
    }

    public function test_slugs_stay_unique_and_non_latin_names_get_a_fallback(): void
    {
        $first = $this->actingAsUser($this->user, 'POST', self::URI, $this->payload(['name' => 'مطعم الشام']));
        $second = $this->actingAsUser($this->user, 'POST', self::URI, $this->payload(['name' => 'مطعم الشام']));

        $first->assertCreated();
        $second->assertCreated();
        $this->assertNotSame($first->json('data.slug'), $second->json('data.slug'));
        $this->assertSame('مطعم الشام', $second->json('data.name'));
    }

    public function test_retrying_with_the_same_idempotency_key_does_not_create_a_duplicate(): void
    {
        $headers = ['Idempotency-Key' => 'onboarding-7f3c2a9e1b'];

        $first = $this->actingAsUser($this->user, 'POST', self::URI, $this->onboardingPayload(), $headers);
        $retry = $this->actingAsUser($this->user, 'POST', self::URI, $this->onboardingPayload(), $headers);

        $first->assertCreated();
        $this->assertApiSuccess($retry, 200)
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('meta.idempotent_replay', true);
        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseCount('services', 2);
    }

    public function test_idempotency_keys_are_scoped_to_the_user(): void
    {
        $headers = ['Idempotency-Key' => 'shared-key-0123456789'];
        $other = User::factory()->create();

        $this->actingAsUser($this->user, 'POST', self::URI, $this->payload(), $headers)->assertCreated();
        $this->actingAsUser($other, 'POST', self::URI, $this->payload(), $headers)->assertCreated();

        $this->assertDatabaseCount('businesses', 2);
    }

    public function test_a_malformed_idempotency_key_is_rejected(): void
    {
        $response = $this->actingAsUser($this->user, 'POST', self::URI, $this->payload(), ['Idempotency-Key' => 'short']);

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_creating_a_business_requires_authentication(): void
    {
        $this->assertApiError($this->postJson(self::URI, $this->payload()), 401, 'unauthenticated');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'missing category' => [['category' => null], 'category'],
            'unknown category' => [['category' => 'space-tourism'], 'category'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'invalid phone' => [['phone' => '12345'], 'phone'],
            'invalid website' => [['website' => 'javascript:alert(1)'], 'website'],
            'invalid timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
            'branch without name' => [['branch' => ['city' => 'Damascus']], 'branch.name'],
            'latitude out of range' => [['branch' => ['name' => 'Main', 'latitude' => 91, 'longitude' => 10]], 'branch.latitude'],
            'latitude without longitude' => [['branch' => ['name' => 'Main', 'latitude' => 33.5]], 'branch.longitude'],
            'invalid country' => [['branch' => ['name' => 'Main', 'country' => 'Syria']], 'branch.country'],
            'duplicate service names' => [['branch' => ['name' => 'Main', 'services' => [['name' => 'Cut'], ['name' => 'cut']]]], 'branch.services.1.name'],
            'service duration too long' => [['branch' => ['name' => 'Main', 'services' => [['name' => 'Cut', 'estimated_duration_minutes' => 5000]]]], 'branch.services.0.estimated_duration_minutes'],
            'services without branch' => [['services' => [['name' => 'Cut']]], 'services'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected(array $overrides, string $field): void
    {
        $response = $this->actingAsUser($this->user, 'POST', self::URI, $this->payload($overrides));

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_a_disabled_category_cannot_be_chosen(): void
    {
        BusinessCategory::query()->where('slug', 'telecommunications')->update(['is_active' => false]);

        $response = $this->actingAsUser($this->user, 'POST', self::URI, $this->payload(['category' => 'telecommunications']));

        $this->assertApiError($response, 422, 'validation_failed')->assertJsonValidationErrors('category');
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function protectedFields(): array
    {
        return [
            'status' => ['status', 'suspended'],
            'slug' => ['slug', 'chosen-slug'],
            'internal id' => ['id', 1],
            'public id' => ['public_id', '01JZZZZZZZZZZZZZZZZZZZZZZZ'],
            'creator' => ['created_by', 1],
            'owner' => ['owner_id', 1],
            'category id' => ['category_id', 1],
            'logo path' => ['logo_path', '../../.env'],
            'branch business id' => ['branch', ['name' => 'Main', 'business_id' => 1]],
            'service branch id' => ['branch', ['name' => 'Main', 'services' => [['name' => 'Cut', 'branch_id' => 1]]]],
        ];
    }

    #[DataProvider('protectedFields')]
    public function test_identity_and_ownership_fields_cannot_be_set(string $field, mixed $value): void
    {
        $response = $this->actingAsUser($this->user, 'POST', self::URI, $this->payload([$field => $value]));

        $this->assertApiError($response, 422, 'validation_failed');
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_the_response_exposes_only_public_identifiers(): void
    {
        $data = $this->actingAsUser($this->user, 'POST', self::URI, $this->onboardingPayload())->json('data');

        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $data['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $data['membership']['id']);
        foreach (['category_id', 'created_by', 'idempotency_key', 'deleted_at', 'logo_path'] as $internal) {
            $this->assertArrayNotHasKey($internal, $data);
        }
        $this->assertArrayNotHasKey('id', $data['category']);
    }
}
