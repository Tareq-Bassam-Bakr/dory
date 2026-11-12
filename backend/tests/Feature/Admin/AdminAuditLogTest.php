<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * The platform audit log (docs/ADMIN_DASHBOARD.md, "Audit log").
 */
class AdminAuditLogTest extends TestCase
{
    use BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    public function test_sensitive_operations_are_listed_with_who_when_and_where(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'UTC'));
        $admin = $this->platformAdmin(['name' => 'Rana Admin', 'email' => 'rana@dory.test']);
        $clinic = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Shifa Clinic']);
        $bakery = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Bakery Basma']);
        $this->asAdmin($admin)->post('/admin/businesses/'.$clinic->public_id.'/approve');
        $this->asAdmin($admin)->post('/admin/businesses/'.$bakery->public_id.'/suspend', ['reason' => 'Unpaid fees']);

        $this->asAdmin($admin)->get('/admin/audit')->assertOk()
            ->assertSee(__('admin.audit.actions.business.approved'))
            ->assertSee(__('admin.audit.actions.business.suspended'))
            ->assertSee('Rana Admin')
            ->assertSee('Unpaid fees')
            ->assertSee('127.0.0.1');
        $this->asAdmin($admin)->get('/admin/audit?action=business.suspended')->assertOk()
            ->assertSee('Bakery Basma')->assertDontSee('Shifa Clinic');
        $this->asAdmin($admin)->get('/admin/audit?business='.$clinic->public_id)->assertOk()
            ->assertSee('Shifa Clinic')->assertDontSee('Unpaid fees');
        $this->asAdmin($admin)->get('/admin/audit?actor=rana@')->assertOk()->assertSee('Shifa Clinic');
        $this->asAdmin($admin)->get('/admin/audit?actor=someone-else')->assertOk()->assertDontSee('Shifa Clinic');
        // An unknown business shows nothing, not everything.
        $this->asAdmin($admin)->get('/admin/audit?business=01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertOk()->assertDontSee('Bakery Basma');
        $this->asAdmin($admin)->get('/admin/audit?action=delete.everything')->assertSessionHasErrors('action');
    }

    public function test_entries_cannot_be_changed_or_deleted_from_the_dashboard(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin/audit')) {
                $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->uri());
            }
        }
        $this->assertNull(PlatformAuditLog::UPDATED_AT);
        $this->assertSame(
            PlatformAuditAction::cases(),
            array_map(fn (string $value) => PlatformAuditAction::from($value), PlatformAuditAction::values()),
        );
    }
}
