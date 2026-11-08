<?php

/*
 * Notification texts (docs/NOTIFICATION_EVENTS.md). Rendered in the reader's
 * language: the device language for push, the app language in the
 * notification center. Placeholders: :business, :queue, :number, :ahead.
 */
return [
    'ticket_almost_turn' => [
        'title' => 'Almost your turn',
        'body' => ':business, ticket :number: :ahead ahead of you.',
        'body_next' => ':business, ticket :number: you are next.',
    ],
    'ticket_called' => [
        'title' => "It's your turn",
        'body' => ':business, ticket :number is called. Please go to the counter.',
    ],
    'ticket_service_started' => [
        'title' => 'Service started',
        'body' => ':business, ticket :number is being served.',
    ],
    'ticket_completed' => [
        'title' => 'Visit completed',
        'body' => ':business, ticket :number is completed. Thank you.',
    ],
    'ticket_cancelled' => [
        'title' => 'Ticket cancelled',
        'body' => ':business cancelled ticket :number.',
    ],
    'ticket_skipped' => [
        'title' => 'Your turn was skipped',
        'body' => ':business skipped ticket :number.',
    ],
    'ticket_no_show' => [
        'title' => 'Marked as not present',
        'body' => ':business called ticket :number, but you were not there.',
    ],
    'queue_paused' => [
        'title' => 'Queue paused',
        'body' => ':business paused :queue. Ticket :number keeps its place.',
    ],
    'queue_closed' => [
        'title' => 'Queue closed',
        'body' => ':business closed :queue. Ticket :number is no longer valid.',
    ],
    'staff_ticket_joined' => [
        'title' => 'New customer',
        'body' => 'Ticket :number joined :queue.',
    ],
    'staff_ticket_cancelled' => [
        'title' => 'Customer cancelled',
        'body' => 'Ticket :number left :queue.',
    ],
];
