<?php

/*
 * Subscriptions and payments (docs/SUBSCRIPTIONS.md, docs/PAYMENTS.md).
 *
 * Customers never pay: discovering businesses, joining queues and following
 * tickets are free, whatever the business's plan. Plans limit what a
 * business can add (branches, queues, team seats) and unlock business-side
 * features; they never block queue operations or customer access.
 *
 * The plans below are only the defaults copied into the database on first
 * migration. After that, the database is the source of truth: platform
 * admins change plans, prices and limits through the admin API without a
 * deployment. Prices are placeholders until the owner sets real ones.
 */
return [

    // Off: every business gets every feature without limits (for example
    // before launch). The subscription records are still kept.
    'enabled' => (bool) env('BILLING_ENABLED', true),

    // Effective plan when there is no paid or trial plan in force.
    'default_plan' => env('BILLING_DEFAULT_PLAN', 'free'),

    // New businesses start with this plan for this many days. 0 disables trials.
    // once_per_owner: an owner's later businesses start on the free plan.
    'trial' => [
        'plan' => env('BILLING_TRIAL_PLAN', 'pro'),
        'days' => (int) env('BILLING_TRIAL_DAYS', 14),
        'once_per_owner' => (bool) env('BILLING_TRIAL_ONCE_PER_OWNER', true),
    ],

    // After a paid period ends unpaid, the plan stays in force this many days.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    // Default currency for checkouts when the request does not name one.
    'currency' => env('BILLING_CURRENCY', 'USD'),

    // An unpaid checkout link stops working after this many minutes.
    'checkout_expires_minutes' => (int) env('BILLING_CHECKOUT_EXPIRES_MINUTES', 60),

    // Online payment provider: "none" (online payment unavailable; payments
    // are recorded by platform admins) or "mastercard" (Mastercard Gateway
    // through an acquiring bank). See docs/PAYMENTS.md.
    'provider' => env('PAYMENT_PROVIDER', 'none'),

    'providers' => [
        'mastercard' => [
            // Given by the acquiring bank, e.g. https://<bank>.gateway.mastercard.com
            'gateway_url' => env('MPGS_GATEWAY_URL'),
            'api_version' => env('MPGS_API_VERSION', '100'),
            'merchant_id' => env('MPGS_MERCHANT_ID'),
            'api_password' => env('MPGS_API_PASSWORD'),
            // Merchant Administration → webhook notifications.
            'notification_secret' => env('MPGS_NOTIFICATION_SECRET'),
            'merchant_name' => env('MPGS_MERCHANT_NAME', 'DORY'),
            'timeout' => (int) env('MPGS_TIMEOUT', 20),
        ],
    ],

    /*
     * Default plans (docs/SUBSCRIPTIONS.md explains the limits). null means
     * unlimited. Amounts are in minor units (cents): 1500 = 15.00.
     */
    'plans' => [
        'free' => [
            'name' => ['en' => 'Free', 'ar' => 'مجاني'],
            'description' => [
                'en' => 'One location with the essentials: queues, QR joining, live updates and notifications.',
                'ar' => 'فرع واحد مع الأساسيات: الطوابير، الانضمام عبر رمز QR، التحديثات المباشرة والإشعارات.',
            ],
            'limits' => ['branches' => 1, 'queues' => 3, 'members' => 3],
            'features' => [],
            'is_free' => true,
            'sort_order' => 1,
            'prices' => [],
        ],
        'pro' => [
            'name' => ['en' => 'Pro', 'ar' => 'احترافي'],
            'description' => [
                'en' => 'For growing businesses: more locations and staff, with insights and peak times.',
                'ar' => 'للأعمال النامية: فروع وموظفون أكثر، مع الرؤى وأوقات الذروة.',
            ],
            'limits' => ['branches' => 3, 'queues' => 15, 'members' => 10],
            'features' => ['insights'],
            'is_free' => false,
            'sort_order' => 2,
            'prices' => [
                ['billing_period' => 'monthly', 'currency' => 'USD', 'amount_minor' => 1500],
                ['billing_period' => 'yearly', 'currency' => 'USD', 'amount_minor' => 15000],
            ],
        ],
        'business' => [
            'name' => ['en' => 'Business', 'ar' => 'الأعمال'],
            'description' => [
                'en' => 'For chains: unlimited locations, queues and staff, with insights per branch.',
                'ar' => 'للسلاسل: فروع وطوابير وموظفون بلا حدود، مع رؤى لكل فرع.',
            ],
            'limits' => ['branches' => null, 'queues' => null, 'members' => null],
            'features' => ['insights', 'branch_insights'],
            'is_free' => false,
            'sort_order' => 3,
            'prices' => [
                ['billing_period' => 'monthly', 'currency' => 'USD', 'amount_minor' => 4900],
                ['billing_period' => 'yearly', 'currency' => 'USD', 'amount_minor' => 49000],
            ],
        ],
    ],
];
