<?php

return [
    'ticket' => [
        'when_event_cancelled' => 1.0,
        'when_validated' => 0.0,
        'after_start' => 0.0,
        'default' => 1.0,
        'before_start_rules' => [
            // Applies when now < start_date
            ['max_hours_before_start' => 24, 'rate' => 0.8],
            ['max_hours_before_start' => 6, 'rate' => 0.5],
        ],
    ],

    'fundraising' => [
        'when_stopped' => 1.0,
        'default' => 0.0,
    ],
];

