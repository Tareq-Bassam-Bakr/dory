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
        'invitations' => (int) env('RATE_LIMIT_INVITATIONS_PER_MINUTE', 10),
        'invitation_token' => (int) env('RATE_LIMIT_INVITATION_TOKEN_PER_MINUTE', 30),
    ],

    /*
     * Team invitations (docs/MEMBERSHIP_AND_INVITATIONS.md).
     *
     * - url: link given to the invitee; "{token}" is replaced by the token.
     *   The default opens the DORY app (custom scheme registered on Android
     *   and iOS).
     * - share_links: return that link to the inviter when the invitation is
     *   created, for sharing by hand. On by default outside production.
     * - send_emails: also email the link. Takes effect only with a real mail
     *   transport (not "log" or "array"), so tokens never end up in log files.
     */
    'invitations' => [
        'url' => env('INVITATION_URL', 'dory://app/invitations/{token}'),
        'expires_in_days' => (int) env('INVITATION_EXPIRES_IN_DAYS', 7),
        'share_links' => (bool) env('INVITATION_SHARE_LINKS', env('APP_ENV', 'production') !== 'production'),
        'send_emails' => (bool) env('INVITATION_SEND_EMAILS', false),
    ],

    /*
     * Local demo data (database/seeders/DemoDataSeeder.php). Leave empty to
     * skip demo accounts. Never set this in production.
     */
    'demo' => [
        'password' => env('SEED_DEMO_PASSWORD'),
    ],

];
