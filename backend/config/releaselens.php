<?php

return [
    'client_url' => env('CLIENT_URL', 'http://localhost:5173'),

    'github' => [
        'app_id' => env('GITHUB_APP_ID'),
        'app_slug' => env('GITHUB_APP_SLUG'),
        'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
        'private_key_base64' => env('GITHUB_APP_PRIVATE_KEY_BASE64'),
        'private_key_path' => env('GITHUB_APP_PRIVATE_KEY_PATH'),
        'api_url' => env('GITHUB_API_URL', 'https://api.github.com'),
        'api_version' => env('GITHUB_API_VERSION', '2026-03-10'),
        'user_agent' => env('GITHUB_USER_AGENT', 'ReleaseLens/1.0'),
        'state_ttl_minutes' => (int) env('GITHUB_STATE_TTL_MINUTES', 10),
        'repository_page_limit' => (int) env('GITHUB_REPOSITORY_PAGE_LIMIT', 10),
    ],

    'demo' => [
        'organization_slug' => env(
            'DEMO_ORGANIZATION_SLUG',
            'northstar-engineering'
        ),

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
