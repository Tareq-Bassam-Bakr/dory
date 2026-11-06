<?php

/*
 * Guest web page behind each queue's QR code (resources/views/guest).
 */
return [
    'brand' => 'DORY',
    'powered_by' => 'Queues by DORY',

    'link_not_found_title' => 'Link not available',
    'link_not_found_body' => 'This link is not valid or has been replaced. Please scan the current QR code at the venue.',
    'ticket_not_found_title' => 'Ticket not found',
    'ticket_not_found_body' => 'There is no ticket for this link saved in this browser.',
    'expired_title' => 'This page has expired',
    'expired_body' => 'Please reload the page and try again.',
    'retry' => 'Try again',

    'join_title' => 'Join the queue',
    'waiting_count' => '{0} Nobody is waiting|{1} 1 person is waiting|[2,*] :count people are waiting',
    'estimated_wait' => '{0} No wait right now|{1} Estimated wait: about 1 minute|[2,*] Estimated wait: about :minutes minutes',
    'estimate_note' => 'Waiting times are estimates and may change.',
    'now_serving' => 'Now serving',
    'status_open' => 'Open',
    'status_paused' => 'Paused: not accepting new customers right now',
    'status_closed' => 'Closed',
    'status_inactive' => 'Not available',
    'name_label' => 'Your name',
    'optional' => '(optional)',
    'phone_label' => 'Phone number',
    'phone_hint' => 'International format, for example +963944123456',
    'consent_label' => 'I agree that :business may use this number to contact me about this ticket only.',
    'join_button' => 'Get my ticket',
    'join_disabled' => 'Please use the DORY app to join this queue.',
    'held_ticket' => 'You already have ticket :number in this queue.',
    'view_ticket' => 'View my ticket',

    'field_errors' => [
        'name' => 'Enter your name (2 to 80 characters).',
        'phone' => 'Enter your phone number in international format, for example +963944123456.',
        'consent' => 'Please agree to how your number is used.',
        'user_id' => 'This field is not allowed.',
        'status' => 'This field is not allowed.',
        'priority' => 'This field is not allowed.',
    ],

    'errors' => [
        'queue_paused' => 'The queue is paused and not accepting new customers right now.',
        'queue_closed' => 'The queue is closed right now.',
        'queue_full' => 'The queue is full right now. Please try again later.',
        'queue_inactive' => 'This queue is not available.',
        'guest_join_disabled' => 'Please use the DORY app to join this queue.',
        'cancellation_not_allowed' => 'This ticket can no longer be cancelled.',
        'generic' => 'Something went wrong. Please try again.',
    ],

    'your_ticket' => 'Your ticket',
    'position' => 'Your position',
    'people_ahead' => '{0} Nobody ahead of you|{1} 1 person ahead of you|[2,*] :count people ahead of you',
    'status' => [
        'waiting' => 'You are in the queue.',
        'called' => 'It is your turn! Please go to the counter now.',
        'serving' => 'You are being served.',
        'completed' => 'Done. Thank you for your visit!',
        'cancelled' => 'This ticket was cancelled.',
        'skipped' => 'This ticket was skipped.',
        'no_show' => 'You missed your turn.',
        'expired' => 'This ticket expired at the end of the day.',
    ],
    'arrive_by' => 'Please arrive before :time.',
    'cancel_button' => 'Leave the queue',
    'cancel_confirm' => 'Leave the queue? Your place will be lost.',
    'auto_refresh' => 'This page updates itself every :seconds seconds.',
    'keep_page' => 'Keep this page open or bookmark it. Your ticket is saved in this browser.',
    'refresh_now' => 'Refresh now',
];
