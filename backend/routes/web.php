<?php

use Illuminate\Support\Facades\Route;

/*
 * DORY's backend is API-only. The root URL just identifies the service.
 * Load balancers can use /up (framework liveness) or /api/v1/health.
 */
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));
