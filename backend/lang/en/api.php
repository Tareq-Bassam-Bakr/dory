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
        'created' => 'Business created successfully',
        'already_created' => 'This business was already created by an earlier identical request',
        'updated' => 'Business updated successfully',
        'deleted' => 'Business deleted successfully',
        'suspended' => 'This business is suspended and cannot be changed',
        'fields_required' => 'Provide at least one field to update',
        'idempotency_key_invalid' => 'The Idempotency-Key header must be 16 to 64 letters, digits, dashes or underscores',
        'idempotency_key_reused' => 'This Idempotency-Key belongs to a deleted business. Use a new key',
    ],

    'branch' => [
        'not_found' => 'Branch not found',
        'created' => 'Branch created successfully',
        'updated' => 'Branch updated successfully',
        'deleted' => 'Branch deleted successfully',
        'fields_required' => 'Provide at least one field to update',
        'coordinates_pair' => 'Latitude and longitude must be updated together',
    ],

    'service' => [
        'not_found' => 'Service not found',
        'created' => 'Service created successfully',
        'updated' => 'Service updated successfully',
        'archived' => 'Service archived successfully',
        'fields_required' => 'Provide at least one field to update',
        'name_taken' => 'This branch already has a service with this name',
    ],

    'member' => [
        'not_found' => 'Member not found',
        'updated' => 'Member updated successfully',
        'removed' => 'Member removed successfully',
        'protected' => 'The owner and your own membership cannot be changed here',
        'fields_required' => 'Provide at least one field to update',
    ],

    'invitation' => [
        'not_found' => 'Invitation not found',
        'created' => 'Invitation created successfully',
        'revoked' => 'Invitation revoked successfully',
        'accepted' => 'Invitation accepted. Welcome to the team',
        'declined' => 'Invitation declined',
        'expired' => 'This invitation has expired',
        'not_pending' => 'This invitation is no longer open',
        'email_mismatch' => 'This invitation was sent to a different email address',
        'already_pending' => 'An invitation for this email is already pending',
        'self_invite' => 'You cannot invite yourself',
        'branches_invalid' => 'Choose branches of this business',
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
