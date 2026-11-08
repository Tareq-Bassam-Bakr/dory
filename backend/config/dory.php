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
        // Joining a queue, per user (or IP for guests) and queue.
        'queue_join' => (int) env('RATE_LIMIT_QUEUE_JOIN_PER_MINUTE', 10),
        // Public queue and ticket lookups, per IP.
        'queue_public' => (int) env('RATE_LIMIT_QUEUE_PUBLIC_PER_MINUTE', 60),
        // Staff queue actions (call next, start, complete...), per user.
        'queue_operations' => (int) env('RATE_LIMIT_QUEUE_OPERATIONS_PER_MINUTE', 120),
        // Public business discovery and search, per IP.
        'discovery' => (int) env('RATE_LIMIT_DISCOVERY_PER_MINUTE', 120),
        // Push device registration, per user (or IP for guests).
        'devices' => (int) env('RATE_LIMIT_DEVICES_PER_MINUTE', 20),
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
     * Queue engine (docs/QUEUE_ENGINE_ARCHITECTURE.md).
     *
     * - public_join_url: the link behind each queue's QR code; "{token}" is
     *   replaced by the queue's public token. The default is the guest web
     *   page served by this backend (routes/web.php), so a QR code works on
     *   any phone camera without the app installed.
     * - wait_history_minimum: completed tickets needed before wait times are
     *   estimated from history instead of the configured service duration.
     * - wait_history_size: how many recent completed tickets the history
     *   average uses.
     * - guest_cookie_days: lifetime of the guest ticket cookie on the web page.
     */
    'queues' => [
        'public_join_url' => env(
            'QUEUE_PUBLIC_JOIN_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/').'/join/{token}',
        ),
        'wait_history_minimum' => (int) env('QUEUE_WAIT_HISTORY_MINIMUM', 5),
        'wait_history_size' => (int) env('QUEUE_WAIT_HISTORY_SIZE', 20),
        'guest_cookie_days' => (int) env('QUEUE_GUEST_COOKIE_DAYS', 2),
    ],

    /*
     * Public business discovery (docs/BUSINESS_DISCOVERY.md).
     *
     * - default_radius_km / max_radius_km: nearby search around a location.
     * - max_per_page: largest page of any discovery list.
     * - wait_candidates: queues or businesses compared when results are
     *   sorted or filtered by estimated wait, which only PHP can compute.
     * - min_ratings: real ratings a business needs before it shows a rating
     *   or takes part in "best rated" sorting.
     */
    'discovery' => [
        'default_radius_km' => (int) env('DISCOVERY_DEFAULT_RADIUS_KM', 10),
        'max_radius_km' => (int) env('DISCOVERY_MAX_RADIUS_KM', 100),
        'max_per_page' => (int) env('DISCOVERY_MAX_PER_PAGE', 50),
        'wait_candidates' => (int) env('DISCOVERY_WAIT_CANDIDATES', 200),
        'min_ratings' => (int) env('DISCOVERY_MIN_RATINGS', 3),
    ],

    /*
     * Real-time updates through Laravel Reverb (docs/REALTIME_ARCHITECTURE.md).
     *
     * - queue: queue name of broadcast jobs; the worker reads it first.
     */
    'realtime' => [
        'queue' => env('REALTIME_QUEUE', 'realtime'),
    ],

    /*
     * Notifications (docs/NOTIFICATION_EVENTS.md).
     *
     * - queue: queue name of notification and push jobs.
     * - almost_turn_people_ahead: "almost your turn" is sent once, when this
     *   many people or fewer are ahead of a waiting ticket.
     * - per_page: notification center page size.
     */
    'notifications' => [
        'queue' => env('NOTIFICATIONS_QUEUE', 'notifications'),
        'almost_turn_people_ahead' => (int) env('NOTIFY_ALMOST_TURN_PEOPLE_AHEAD', 2),
        'per_page' => 20,
    ],

    /*
     * Push notifications through Firebase Cloud Messaging
     * (docs/PUSH_NOTIFICATIONS.md, docs/FIREBASE_SETUP.md).
     *
     * - enabled: off until a Firebase project and a service account exist.
     *   FIREBASE_CREDENTIALS must point to the service account JSON, kept
     *   outside the repository. Nothing is sent while this is off.
     * - android_channel_id: the app's notification channel.
     */
    'push' => [
        'enabled' => (bool) env('FCM_ENABLED', false),
        'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'dory_queue_updates'),
    ],

    /*
     * Local demo data (database/seeders/DemoDataSeeder.php). Leave empty to
     * skip demo accounts. Never set this in production.
     */
    'demo' => [
        'password' => env('SEED_DEMO_PASSWORD'),
    ],

];
