<?php

/*
 * DORY application settings. Every value is environment-driven; see
 * .env.example and docs/LOCAL_BACKEND_SETUP.md.
 */
return [

    /*
     * Where the password reset email sends the user. The token and email are
     * appended as query parameters. Point this at the Flutter deep link or
     * web page that collects the new password.
     */
    'password_reset_url' => env(
        'PASSWORD_RESET_URL',
        rtrim((string) env('APP_URL', 'http://localhost'), '/').'/reset-password',
    ),

    /*
     * Requests per minute. Auth and password limits are per email+IP and
     * additionally per IP.
     */
    'rate_limits' => [
        'api' => (int) env('RATE_LIMIT_API_PER_MINUTE', 60),
        'auth' => (int) env('RATE_LIMIT_AUTH_PER_MINUTE', 5),
        'auth_per_ip' => (int) env('RATE_LIMIT_AUTH_PER_IP_PER_MINUTE', 20),
        'password_reset' => (int) env('RATE_LIMIT_PASSWORD_RESET_PER_MINUTE', 3),
    ],

    /*
     * Local demo data (database/seeders/DemoDataSeeder.php). Leave empty to
     * skip demo accounts. Never set this in production.
     */
    'demo' => [
        'password' => env('SEED_DEMO_PASSWORD'),
    ],

];
