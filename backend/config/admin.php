<?php

/*
 * The platform admin dashboard at /admin (docs/ADMIN_DASHBOARD.md).
 *
 * It has its own sign-in sessions; the API stays token-based and keeps no
 * sessions. Only accounts flagged is_platform_admin (set from the command
 * line, `php artisan dory:admin`) may sign in.
 */
return [

    'session' => [
        // Stored server-side, so an admin's sessions can be ended at once.
        'driver' => env('ADMIN_SESSION_DRIVER', 'database'),
        'table' => 'admin_sessions',
        'cookie' => env('ADMIN_SESSION_COOKIE', 'dory_admin_session'),
        // Signed out after this many minutes without activity.
        'idle_minutes' => (int) env('ADMIN_SESSION_IDLE_MINUTES', 30),
        // And after this many hours in any case.
        'absolute_hours' => (int) env('ADMIN_SESSION_ABSOLUTE_HOURS', 8),
        // null: secure (HTTPS only) when APP_URL starts with https.
        'secure' => in_array(env('ADMIN_SESSION_SECURE_COOKIE'), [null, ''], true)
            ? null
            : (bool) env('ADMIN_SESSION_SECURE_COOKIE'),
    ],

    'rate_limits' => [
        // Sign-in attempts per email and address, per minute.
        'login' => (int) env('ADMIN_LOGIN_PER_MINUTE', 5),
        // Sign-in attempts per address across accounts, per minute.
        'login_per_ip' => (int) env('ADMIN_LOGIN_PER_IP_PER_MINUTE', 20),
        // Dashboard requests per admin, per minute.
        'requests' => (int) env('ADMIN_REQUESTS_PER_MINUTE', 120),
    ],

    'per_page' => 25,
];
