<?php

/*
 * Messages returned in the `message` field of API responses.
 *
 * Clients should branch on the machine-readable `code` field, not on these
 * texts. Arabic translations will be added when localized API messages are
 * introduced (see docs/API_CONVENTIONS.md).
 */
return [
    'success' => 'Operation completed successfully',

    'auth' => [
        'registered' => 'Account created successfully',
        'logged_in' => 'Logged in successfully',
        'logged_out' => 'Logged out successfully',
        'invalid_credentials' => 'The provided credentials are incorrect',
        'account_inactive' => 'This account is not active',
        'reset_link_sent' => 'If an account exists for this email, a password reset link has been sent',
        'password_reset' => 'Your password has been reset. Please log in again',
        'reset_token_invalid' => 'This password reset link is invalid or has expired',
    ],

    'users' => [
        'profile_updated' => 'Profile updated successfully',
        'locale_updated' => 'Language updated successfully',
        'profile_fields_required' => 'Provide at least one field to update',
    ],

    'business' => [
        'not_found' => 'Business not found',
        'duplicate_membership' => 'This user is already a member of the business',
    ],

    'health' => [
        'ok' => 'Service is healthy',
        'unhealthy' => 'Service is unhealthy',
    ],

    'errors' => [
        'validation' => 'Validation failed',
        'unauthenticated' => 'Unauthenticated',
        'forbidden' => 'This action is not allowed',
        'not_found' => 'Resource not found',
        'method_not_allowed' => 'Method not allowed',
        'too_many_requests' => 'Too many requests. Please try again later',
        'service_unavailable' => 'Service temporarily unavailable',
        'server' => 'An unexpected error occurred',
        'http' => 'The request could not be processed',
    ],
];
