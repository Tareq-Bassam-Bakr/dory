<?php

/*
 * Business insights (docs/AI_ARCHITECTURE.md), worded by the template
 * narrator. Placeholders are the insight's facts.
 */
return [
    'no_show_high' => [
        'title' => 'Many customers miss their turn',
        'body' => ':rate% of the customers who were called did not show up. A longer time to arrive after the call, or the "almost your turn" alert, can help.',
    ],
    'long_waits' => [
        'title' => 'Waits are long',
        'body' => 'Customers waited :minutes minutes on average. More staff around :hour, your busiest hour, would shorten the line.',
    ],
    'cancellations_high' => [
        'title' => 'Customers leave the queue',
        'body' => ':rate% of customers cancelled their ticket. Long waits are a common reason.',
    ],
    'peak_concentration' => [
        'title' => 'Your busiest hours',
        'body' => ':share% of customers arrive at :hours. Plan more staff at these hours.',
    ],
    'demand_up' => [
        'title' => 'Demand is growing',
        'body' => ':percent% more customers than in the previous period. Check that your staffing keeps up.',
    ],
    'demand_down' => [
        'title' => 'Fewer customers',
        'body' => ':percent% fewer customers than in the previous period.',
    ],
    'service_time_drift' => [
        'title' => 'Update a service duration',
        'body' => 'In :queue, a customer takes about :actual minutes, but the queue is set to :configured. Updating it keeps wait estimates accurate while a queue has little history.',
    ],
    'running_well' => [
        'title' => 'Your queues run well',
        'body' => ':rate% of customers were served, after :minutes minutes of waiting on average.',
    ],
];
