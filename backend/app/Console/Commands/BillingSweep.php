<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionService;
use App\Services\Payments\CheckoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled every 15 minutes (routes/console.php). Stores subscription
 * transitions that time has caused (trial over, period ended, grace over)
 * and settles payment checkouts whose link expired. Entitlements never wait
 * for it: SubscriptionResolver already computes the same state on each
 * request. Safe to run any number of times.
 */
class BillingSweep extends Command
{
    protected $signature = 'billing:sweep';

    protected $description = 'Store due subscription transitions and settle expired payment checkouts';

    public function handle(SubscriptionService $subscriptions, CheckoutService $checkouts): int
    {
        if (! Schema::hasTable('business_subscriptions') || ! Schema::hasTable('payment_checkouts')) {
            $this->components->warn('The billing tables are not migrated yet. Nothing to do.');

            return self::SUCCESS;
        }

        $changed = $subscriptions->sweep();
        $settled = $checkouts->expireStale();
        $this->components->info(sprintf('Subscriptions changed: %d. Checkouts settled: %d.', $changed, $settled));

        return self::SUCCESS;
    }
}
