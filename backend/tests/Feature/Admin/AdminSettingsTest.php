<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Platform\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * Run-time settings (docs/ADMIN_DASHBOARD.md, "Settings"): stored values
 * override config/ for every request; the server configuration is shown,
 * never its secrets.
 */
class AdminSettingsTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'UTC'));
        $this->admin = $this->platformAdmin(['name' => 'Rana Admin']);
    }

    /**
     * The form as the page sends it, with $changes applied.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function form(array $changes = []): array
    {
        return array_replace([
            'billing_enabled' => '1',
            'trial_plan' => 'pro',
            'trial_days' => '14',
            'trial_once_per_owner' => '1',
            'grace_days' => '7',
            'currencies' => 'USD',
            'default_currency' => 'USD',
            'four_eyes' => '0',
            'require_business_approval' => '0',
        ], $changes);
    }

    public function test_the_page_shows_values_and_server_defaults(): void
    {
        $this->asAdmin($this->admin)->get('/admin/settings')->assertOk()
            ->assertSee('name="trial_days"', false)
            ->assertSee('value="14"', false)
            ->assertSee(__('admin.settings.default', ['value' => '14']));
    }

    public function test_saving_the_form_unchanged_changes_nothing(): void
    {
        $this->asAdmin($this->admin)->put('/admin/settings', $this->form())
            ->assertRedirect('/admin/settings')
            ->assertSessionHas('status', __('admin.settings.unchanged'));
        $this->assertSame(0, PlatformSetting::query()->count());
        $this->assertFalse(PlatformAuditLog::query()->exists());
    }

    public function test_a_saved_value_applies_to_the_next_request_and_is_audited(): void
    {
        $this->asAdmin($this->admin)->put('/admin/settings', $this->form(['trial_days' => '30', 'four_eyes' => '1']))
            ->assertSessionHas('status', trans_choice('admin.settings.saved', 2, ['count' => 2]));

        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::SettingsUpdated->value)->sole();
        // MySQL stores JSON objects with their keys reordered: compare by key.
        $changes = $entry->properties['changes'];
        $this->assertSame([14, 30], [$changes['trial_days']['from'], $changes['trial_days']['to']]);
        $this->assertSame([false, true], [$changes['four_eyes']['from'], $changes['four_eyes']['to']]);
        $this->assertCount(2, $changes);
        $this->assertSame($this->admin->getKey(), $entry->actor_id);

        // A new process reads the stored values.
        config(['billing.trial.days' => 14, 'billing.manual_payments.four_eyes' => false]);
        app(PlatformSettings::class)->apply();
        $this->assertSame(30, config('billing.trial.days'));
        $this->assertTrue(config('billing.manual_payments.four_eyes'));

        // New businesses get the new trial.
        $business = $this->businessOwnedBy(User::factory()->create());
        $this->assertTrue($this->subscriptionOf($business)->trial_ends_at->equalTo(now()->addDays(30)));

        $this->asAdmin($this->admin)->get('/admin/settings')->assertOk()
            ->assertSee(__('admin.settings.overridden'))
            ->assertSee('Rana Admin');
    }

    public function test_setting_a_value_back_to_its_default_removes_the_override(): void
    {
        $this->asAdmin($this->admin)->put('/admin/settings', $this->form(['grace_days' => '3']));
        $this->assertSame(1, PlatformSetting::query()->count());

        $this->asAdmin($this->admin)->put('/admin/settings', $this->form(['grace_days' => '7']));
        $this->assertSame(0, PlatformSetting::query()->count());
        $this->assertSame(7, config('billing.grace_days'));
        $this->assertSame(2, PlatformAuditLog::query()->where('action', PlatformAuditAction::SettingsUpdated->value)->count());
    }

    public function test_settings_are_checked(): void
    {
        $put = fn (array $changes) => $this->asAdmin($this->admin)->put('/admin/settings', $this->form($changes));

        $put(['trial_days' => '365'])->assertSessionHasErrors('trial_days');
        $put(['grace_days' => '-1'])->assertSessionHasErrors('grace_days');
        $put(['trial_plan' => 'free'])->assertSessionHasErrors('trial_plan');
        $put(['trial_plan' => 'nothing'])->assertSessionHasErrors('trial_plan');
        $put(['currencies' => 'USD, DOLLARS'])->assertSessionHasErrors('currencies.1');
        // The default currency must be accepted.
        $put(['default_currency' => 'IQD'])->assertSessionHasErrors('default_currency');
        $this->assertSame(0, PlatformSetting::query()->count());

        $put(['currencies' => 'usd iqd', 'default_currency' => 'IQD'])->assertSessionHasNoErrors();
        $this->assertSame(['USD', 'IQD'], config('billing.currencies'));
        $this->assertSame('IQD', config('billing.currency'));
    }

    public function test_switching_plans_off_gives_every_business_everything(): void
    {
        $business = $this->businessOwnedBy(User::factory()->create());
        $this->travel(15)->days();
        $this->asAdmin($this->admin)->put('/admin/settings', $this->form(['billing_enabled' => '0']));

        $owner = $business->members()->first()->user;
        $this->actingAsUser($owner, 'GET', '/api/v1/businesses/'.$business->public_id.'/subscription')
            ->assertJsonPath('data.plan.code', 'unlimited');
    }

    public function test_server_configuration_is_shown_without_its_secrets(): void
    {
        config([
            'billing.provider' => 'mastercard',
            'billing.providers.mastercard.gateway_url' => 'https://bank.gateway.example',
            'billing.providers.mastercard.merchant_id' => 'MERCHANT-SECRET-ID',
            'billing.providers.mastercard.api_password' => 'api-password-never-shown',
            'billing.providers.mastercard.notification_secret' => 'webhook-secret-never-shown',
        ]);

        $html = (string) $this->asAdmin($this->admin)->get('/admin/settings')->assertOk()
            ->assertSee(__('admin.settings.server.set'))
            ->assertSee(__('admin.settings.server.online_off'))
            ->getContent();
        foreach (['MERCHANT-SECRET-ID', 'api-password-never-shown', 'webhook-secret-never-shown', (string) config('app.key'), (string) config('database.connections.mysql.password')] as $secret) {
            if ($secret !== '') {
                $this->assertStringNotContainsString($secret, $html);
            }
        }
    }

    public function test_the_live_payments_switch_cannot_be_changed_from_the_dashboard(): void
    {
        $this->asAdmin($this->admin)->put('/admin/settings', $this->form() + ['online_payments' => '1', 'provider' => 'mastercard']);

        $this->assertFalse(config('billing.online_payments'));
        $this->assertSame(0, PlatformSetting::query()->count());
        $this->assertArrayNotHasKey('online_payments', PlatformSettings::FIELDS);
    }
}
