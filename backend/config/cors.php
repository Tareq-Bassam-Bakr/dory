<?php

/*
 * CORS only matters for browsers. The Flutter mobile app is not subject to
 * CORS, so by default no web origin is allowed. List trusted web origins
 * (e.g. a future admin dashboard) in CORS_ALLOWED_ORIGINS, comma-separated.
 */
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-Request-Id',
    ],

    'exposed_headers' => ['X-Request-Id', 'Retry-After'],

    'max_age' => 3600,

    // Bearer tokens, not cookies: credentials are never sent cross-origin.
    'supports_credentials' => false,

];
