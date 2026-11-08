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

    'queue' => [
        'queue_not_found' => 'Queue not found',
        'business_not_found' => 'Business not found',
        'branch_not_found' => 'Branch not found',
        'session_not_found' => 'Queue session not found',
        'ticket_not_found' => 'Ticket not found',
        'assignment_not_found' => 'Staff assignment not found',
        'created' => 'Queue created successfully',
        'updated' => 'Queue updated successfully',
        'deleted' => 'Queue deleted successfully',
        'opened' => 'Queue opened',
        'paused' => 'Queue paused: no new customers can join',
        'resumed' => 'Queue resumed',
        'closed' => 'Queue closed',
        'joined' => 'You joined the queue',
        'already_joined' => 'This ticket was already created by an earlier identical request',
        'ticket_cancelled' => 'Ticket cancelled',
        'ticket_updated' => 'Ticket updated',
        'staff_assigned' => 'Staff member assigned to the queue',
        'staff_unassigned' => 'Staff member removed from the queue',
        'token_rotated' => 'A new public link was created; the previous link no longer works',
        'queue_inactive' => 'This queue is not active',
        'queue_closed' => 'This queue is closed',
        'queue_paused' => 'This queue is paused and not accepting new customers right now',
        'queue_full' => 'This queue is full right now. Please try again later',
        'business_unavailable' => 'This business is not taking customers right now',
        'branch_inactive' => 'This branch is not taking customers right now',
        'service_unavailable' => 'This service is not available right now',
        'ticket_not_reviewable' => 'Only completed tickets can be rated',
        'already_reviewed' => 'This ticket was already rated',
        'review_saved' => 'Thank you for your rating',
        'guest_join_disabled' => 'This queue requires signing in to join',
        'duplicate_active_ticket' => 'You already have an active ticket in this queue',
        'invalid_ticket_transition' => 'A ticket cannot go from :from to :to',
        'ticket_state_changed' => 'This ticket was just changed by someone else. Refresh and try again',
        'no_waiting_tickets' => 'Nobody is waiting',
        'staff_busy' => 'Finish your current customer before calling the next one',
        'ticket_handled_by_other_staff' => 'Another staff member is handling this ticket',
        'grace_period_active' => 'The customer still has :seconds seconds to arrive',
        'session_already_active' => 'This queue already has an open or paused session',
        'no_active_session' => 'This queue has no open or paused session',
        'session_not_open' => 'This session is not open',
        'session_not_paused' => 'This session is not paused',
        'queue_has_active_session' => 'Close the queue session before deleting the queue',
        'cancellation_not_allowed' => 'This ticket can no longer be cancelled',
        'prefix_taken' => 'Another queue in this branch already uses this prefix',
        'service_has_queue' => 'This service already has a queue',
        'service_not_in_branch' => 'Choose a service of this branch',
        'member_not_eligible' => 'Choose an active manager or employee who works in this branch',
        'consent_required' => 'Please accept how your details are used',
    ],

    'favorites' => [
        'saved' => 'Business saved',
        'already_saved' => 'This business is already saved',
        'removed' => 'Business removed from your saved list',
    ],

    'notifications' => [
        'listed' => 'Notifications',
        'read' => 'Notification marked as read',
        'all_read' => 'All notifications marked as read',
        'preferences' => 'Notification preferences',
        'preferences_updated' => 'Notification preferences updated',
        'device_registered' => 'Device registered for notifications',
        'device_unregistered' => 'Device removed from notifications',
        'device_not_found' => 'Device not found',
        'notification_not_found' => 'Notification not found',
        'ticket_finished' => 'This ticket is finished: no more notifications will be sent for it',
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
