<?php

use Illuminate\Support\Facades\Schedule;

// Requires the scheduler: `php artisan schedule:work` locally (start_dory opens
// it as DORY-SCHEDULER), or a cron entry running `php artisan schedule:run`
// every minute in production.

// Housekeeping that deletes rows runs in production only, so the local
// scheduler never deletes anything from a developer's database.
// Delete Sanctum tokens that expired more than 24 hours ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->environments(['production']);

// Delete expired password reset tokens.
Schedule::command('auth:clear-resets')->daily()->environments(['production']);

// Subscriptions (docs/SUBSCRIPTIONS.md): store trial ends, unpaid periods and
// grace ends, and settle payment links that expired. Plans are already right
// before this runs; this keeps the records and the history right.
Schedule::command('billing:sweep')->everyFifteenMinutes()->withoutOverlapping();
