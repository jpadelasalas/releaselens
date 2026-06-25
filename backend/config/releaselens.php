<?php

return [
    'demo' => [
        'anchor_date' => env(
            'DEMO_SEED_ANCHOR',
            '2026-06-19T12:00:00Z'
        ),

        'random_seed' => (int) env(
            'DEMO_SEED_RANDOM_SEED',
            1001
        ),
    ],
];