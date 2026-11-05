<?php

use Illuminate\Support\Facades\Schedule;

// Housekeeping. Requires the scheduler: `php artisan schedule:work` locally,
// or a cron entry running `php artisan schedule:run` every minute in production.

// Delete Sanctum tokens that expired more than 24 hours ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Delete expired password reset tokens.
Schedule::command('auth:clear-resets')->daily();
