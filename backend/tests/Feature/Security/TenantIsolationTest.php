<?php

namespace Tests\Feature\Security;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessInvitation;
use App\Models\BusinessMember;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Every tenant-scoped endpoint, attacked from another tenant.
 *
 * For each endpoint, a user who owns a different business tries to reach
 * the victim's resource. The request must fail with the same 404 as for a
 * resource that does not exist, and must change nothing.
 */
class TenantIsolationTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private const MISSING_ID = '01jzzzzzzzzzzzzzzzzzzzzzzz';

    private User $attacker;

    private Business $victim;

    private Branch $victimBranch;

    private Service $victimService;

    private BusinessMember $victimMember;

    private BusinessInvitation $victimInvitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->victim = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Victim Clinic']);
        $this->victimBranch = $this->branchOf($this->victim);
        $this->victimService = $this->serviceOf($this->victimBranch);
        $employee = $this->memberOf($this->victim, BusinessRole::Employee);
        $this->victimMember = $this->membershipOf($employee, $this->victim);
        $this->victimInvitation = BusinessInvitation::factory()->for($this->victim)->create();

        // The attacker is a legitimate owner of another business.
        $this->attacker = User::factory()->create();
        $this->businessOwnedBy($this->attacker, ['name' => 'Attacker Repairs']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: array<string, mixed>}>
     *                                                                                        method, path template, expected code, payload
     */
    public static function endpoints(): array
    {
        return [
            'view business' => ['GET', 'businesses/{business}', 'business_not_found', []],
            'update business' => ['PUT', 'businesses/{business}', 'business_not_found', ['name' => 'Pwned']],
            'deactivate business' => ['PUT', 'businesses/{business}', 'business_not_found', ['status' => 'inactive']],
            'delete business' => ['DELETE', 'businesses/{business}', 'business_not_found', []],
            'list branches' => ['GET', 'businesses/{business}/branches', 'business_not_found', []],
            'create branch' => ['POST', 'businesses/{business}/branches', 'business_not_found', ['name' => 'Rogue']],
            'view branch' => ['GET', 'branches/{branch}', 'branch_not_found', []],
            'update branch' => ['PUT', 'branches/{branch}', 'branch_not_found', ['name' => 'Pwned']],
            'delete branch' => ['DELETE', 'branches/{branch}', 'branch_not_found', []],
            'list services' => ['GET', 'branches/{branch}/services', 'branch_not_found', []],
            'create service' => ['POST', 'branches/{branch}/services', 'branch_not_found', ['name' => 'Rogue']],
            'view service' => ['GET', 'services/{service}', 'service_not_found', []],
            'update service' => ['PUT', 'services/{service}', 'service_not_found', ['name' => 'Pwned']],
            'archive service' => ['DELETE', 'services/{service}', 'service_not_found', []],
            'list members' => ['GET', 'businesses/{business}/members', 'business_not_found', []],
            'change member' => ['PUT', 'businesses/{business}/members/{member}', 'business_not_found', ['role' => 'manager']],
            'remove member' => ['DELETE', 'businesses/{business}/members/{member}', 'business_not_found', []],
            'list invitations' => ['GET', 'businesses/{business}/invitations', 'business_not_found', []],
            'invite' => ['POST', 'businesses/{business}/invitations', 'business_not_found', ['email' => 'mole@example.com', 'role' => 'manager']],
            'revoke invitation' => ['DELETE', 'businesses/{business}/invitations/{invitation}', 'business_not_found', []],
        ];
    }

    private function path(string $template, bool $missing = false): string
    {
        return '/api/v1/'.strtr($template, [
            '{business}' => $missing ? self::MISSING_ID : $this->victim->public_id,
            '{branch}' => $missing ? self::MISSING_ID : $this->victimBranch->public_id,
            '{service}' => $missing ? self::MISSING_ID : $this->victimService->public_id,
            '{member}' => $this->victimMember->public_id,
            '{invitation}' => $this->victimInvitation->public_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $tables = ['businesses', 'branches', 'services', 'business_members', 'business_member_branch', 'business_invitations'];

        return collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('endpoints')]
    public function test_another_tenant_cannot_reach_the_resource(string $method, string $template, string $code, array $payload): void
    {
        $before = $this->snapshot();

        $response = $this->actingAsUser($this->attacker, $method, $this->path($template), $payload);

        $this->assertApiError($response, 404, $code);
        $this->assertEquals($before, $this->snapshot(), 'A cross-tenant request changed data.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('endpoints')]
    public function test_the_denial_is_indistinguishable_from_a_missing_resource(string $method, string $template, string $code, array $payload): void
    {
        $denied = $this->actingAsUser($this->attacker, $method, $this->path($template), $payload);
        $missing = $this->actingAsUser($this->attacker, $method, $this->path($template, missing: true), $payload);

        $this->assertSame($missing->status(), $denied->status());
        $this->assertSame($missing->json(), $denied->json());
    }

    public function test_client_supplied_tenant_ids_are_ignored_everywhere(): void
    {
        $attackerBusiness = Business::query()->where('name', 'Attacker Repairs')->sole();
        $attackerBranch = $this->branchOf($attackerBusiness);
        $smuggled = [
            'business_id' => $this->victim->id,
            'business' => $this->victim->public_id,
            'branch_id' => $this->victimBranch->id,
        ];
        $headers = ['X-Business-Id' => (string) $this->victim->id, 'X-Branch-Id' => (string) $this->victimBranch->id];

        // Reads of the attacker's own resources stay on the attacker's tenant.
        $branches = $this->actingAsUser($this->attacker, 'GET', '/api/v1/businesses/'.$attackerBusiness->public_id.'/branches?'.http_build_query($smuggled), [], $headers);
        $this->assertSame([$attackerBranch->public_id], $branches->json('data.*.id'));

        // Writes that try to retarget a record are rejected outright.
        $this->assertApiError(
            $this->actingAsUser($this->attacker, 'POST', '/api/v1/branches/'.$attackerBranch->public_id.'/services', ['name' => 'Smuggled', 'branch_id' => $this->victimBranch->id], $headers),
            422,
            'validation_failed',
        );
        $this->assertSame(1, $this->victimBranch->services()->count());
    }

    public function test_a_branch_manager_cannot_cross_to_sibling_branches(): void
    {
        $owner = User::factory()->create();
        $business = $this->businessOwnedBy($owner, ['name' => 'Two Branch Co']);
        $own = $this->branchOf($business);
        $sibling = $this->branchOf($business);
        $siblingService = $this->serviceOf($sibling);
        $branchManager = $this->memberOf($business, BusinessRole::Manager, BranchAccess::Selected, [$own]);

        foreach ([
            ['GET', '/api/v1/branches/'.$sibling->public_id, 'branch_not_found'],
            ['PUT', '/api/v1/branches/'.$sibling->public_id, 'branch_not_found'],
            ['GET', '/api/v1/branches/'.$sibling->public_id.'/services', 'branch_not_found'],
            ['POST', '/api/v1/branches/'.$sibling->public_id.'/services', 'branch_not_found'],
            ['GET', '/api/v1/services/'.$siblingService->public_id, 'service_not_found'],
            ['PUT', '/api/v1/services/'.$siblingService->public_id, 'service_not_found'],
            ['DELETE', '/api/v1/services/'.$siblingService->public_id, 'service_not_found'],
        ] as [$method, $uri, $code]) {
            $this->assertApiError($this->actingAsUser($branchManager, $method, $uri, ['name' => 'Crossed']), 404, $code);
        }

        $this->assertSame([$sibling->name, $siblingService->name], [$sibling->fresh()->name, $siblingService->fresh()->name]);
    }

    public function test_a_platform_admin_gets_no_management_rights_from_the_flag(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->assertApiError($this->actingAsUser($admin, 'PUT', '/api/v1/businesses/'.$this->victim->public_id, ['name' => 'Admin edit']), 404, 'business_not_found');
        $this->assertApiError($this->actingAsUser($admin, 'DELETE', '/api/v1/services/'.$this->victimService->public_id), 404, 'service_not_found');
        $this->assertSame('Victim Clinic', $this->victim->fresh()->name);
    }

    public function test_no_business_endpoint_can_grant_platform_admin(): void
    {
        $owner = User::factory()->create();

        $this->actingAsUser($owner, 'POST', '/api/v1/businesses', [
            'name' => 'Escalation Inc',
            'category' => BusinessCategory::factory()->create()->slug,
            'is_platform_admin' => true,
        ]);

        $this->assertFalse($owner->fresh()->is_platform_admin);
    }
}
