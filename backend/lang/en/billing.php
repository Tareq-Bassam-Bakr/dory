<?php

/*
 * Payment pages around an online checkout (resources/views/billing).
 */
return [
    'pay_title' => 'Secure payment',
    'pay_intro' => 'You will enter your card details on the payment page of our bank partner. DORY never sees them.',
    'pay_button' => 'Continue to payment',
    'pay_error' => 'The payment page could not be opened. Check your connection and try again.',
    'plan' => 'Plan',
    'period' => 'Billing period',
    'amount' => 'Amount',
    'periods' => [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ],
    'check_again' => 'Check again',
    'back_to_app' => 'You can now return to the DORY app. Your plan updates there automatically.',
    'secure_note' => 'Payments are processed by the payment provider. DORY stores no card details.',
    'result' => [
        'paid' => [
            'badge' => 'Paid',
            'title' => 'Payment received',
            'body' => 'Thank you. Your plan is active.',
        ],
        'confirming' => [
            'badge' => 'Confirming',
            'title' => 'Confirming your payment',
            'body' => 'The payment was submitted. We are confirming it with the bank; this usually takes less than a minute.',
        ],
        'pending' => [
            'badge' => 'Not completed',
            'title' => 'Payment not completed yet',
            'body' => 'No payment has been confirmed for this checkout. If you paid, it will appear shortly.',
        ],
        'failed' => [
            'badge' => 'Failed',
            'title' => 'The payment did not go through',
            'body' => 'Your plan was not changed and nothing was charged by DORY. You can try again from the app.',
        ],
        'expired' => [
            'badge' => 'Expired',
            'title' => 'This payment link expired',
            'body' => 'Start a new payment from the DORY app.',
        ],
        'unavailable' => [
            'badge' => 'Unavailable',
            'title' => 'Online payment is not available',
            'body' => 'Please contact DORY support to pay by bank transfer or another method.',
        ],
    ],
];
