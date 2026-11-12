<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\PaymentGateway;
use App\Models\SubscriptionPlan;
use App\Services\Payments\CheckoutService;
use App\Services\Platform\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings (docs/ADMIN_DASHBOARD.md, "Settings"): the few values platform
 * admins may change while DORY runs, each recorded in the audit log, and a
 * read-only view of the server configuration that matters for safety. No
 * secret is ever shown: only whether one is set.
 */
class SettingsController extends AdminController
{
    private const GROUPS = [
        'billing' => ['billing_enabled', 'trial_plan', 'trial_days', 'trial_once_per_owner', 'grace_days'],
        'payments' => ['currencies', 'default_currency', 'four_eyes'],
        'businesses' => ['require_business_approval'],
    ];

    public function __construct(private readonly PlatformSettings $settings) {}

    public function edit(CheckoutService $checkouts, PaymentGateway $gateway): View
    {
        return view('admin.settings', [
            'settings' => $this->settings->current(),
            'definitions' => PlatformSettings::FIELDS,
            'groups' => self::GROUPS,
            'paidPlans' => SubscriptionPlan::query()->where('is_free', false)->orderBy('sort_order')->orderBy('id')->get(),
            'currencyChoices' => (array) config('billing.currencies'),
            'environment' => $this->environment($checkouts, $gateway),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $changes = $this->settings->update($request->only(array_keys(PlatformSettings::FIELDS)), $this->admin($request));

        return redirect()->route('admin.settings')->with('status', $changes === []
            ? __('admin.settings.unchanged')
            : trans_choice('admin.settings.saved', count($changes), ['count' => count($changes)]));
    }

    /**
     * @return list<array{label: string, value: string, tone?: string, hint?: string}>
     */
    private function environment(CheckoutService $checkouts, PaymentGateway $gateway): array
    {
        $production = app()->environment('production');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $secureCookie = config('admin.session.secure') === null ? $https : (bool) config('admin.session.secure');
        $debug = (bool) config('app.debug');
        $online = $checkouts->available();
        $label = fn (string $key): string => __('admin.settings.server.'.$key);
        $onOff = fn (bool $on): string => $on ? __('admin.common.on') : __('admin.common.off');

        return [
            ['label' => $label('environment'), 'value' => (string) config('app.env')],
            ['label' => $label('debug'), 'value' => $onOff($debug), 'tone' => $debug ? ($production ? 'danger' : 'warning') : 'success',
                'hint' => $debug ? __('admin.settings.server.debug_hint') : null],
            ['label' => $label('https'), 'value' => $https ? __('admin.common.yes') : __('admin.common.no'), 'tone' => $https ? 'success' : ($production ? 'danger' : 'warning')],
            ['label' => $label('admin_cookie'), 'value' => $secureCookie ? __('admin.settings.server.secure_cookie') : __('admin.settings.server.plain_cookie'),
                'tone' => $secureCookie ? 'success' : ($production ? 'danger' : 'warning')],
            ['label' => $label('session'), 'value' => __('admin.settings.server.session_value', [
                'idle' => (int) config('admin.session.idle_minutes'),
                'hours' => (int) config('admin.session.absolute_hours'),
            ])],
            ['label' => $label('online_payments'), 'value' => $online ? __('admin.settings.server.online_on') : __('admin.settings.server.online_off'),
                'tone' => $online ? 'warning' : 'info', 'hint' => __('admin.settings.server.online_hint')],
            ['label' => $label('provider'), 'value' => $gateway->name()],
            ['label' => $label('provider_credentials'), 'value' => $gateway->isConfigured() ? __('admin.settings.server.set') : __('admin.settings.server.not_set'),
                'hint' => __('admin.settings.server.never_shown')],
            ['label' => $label('queue'), 'value' => (string) config('queue.default')],
            ['label' => $label('cache'), 'value' => (string) config('cache.default')],
            ['label' => $label('broadcasting'), 'value' => (string) config('broadcasting.default')],
            ['label' => $label('mail'), 'value' => (string) config('mail.default')],
            ['label' => $label('logging'), 'value' => (string) config('logging.default')],
        ];
    }
}
