<?php

return [
    'demo' => [
        'email' => env('SELLERSCOPE_DEMO_EMAIL'),
        'password' => env('SELLERSCOPE_DEMO_PASSWORD'),
        'connection_token' => env('SELLERSCOPE_DEMO_TOKEN'),
    ],

    'wildberries' => [
        'hosts' => [
            'common' => env('WB_COMMON_API_URL', 'https://common-api.wildberries.ru'),
            'content' => env('WB_CONTENT_API_URL', 'https://content-api.wildberries.ru'),
            'statistics' => env('WB_STATISTICS_API_URL', 'https://statistics-api.wildberries.ru'),
            'analytics' => env('WB_ANALYTICS_API_URL', 'https://seller-analytics-api.wildberries.ru'),
        ],
        'sandbox_hosts' => [
            'content' => env('WB_SANDBOX_CONTENT_API_URL', 'https://content-api-sandbox.wildberries.ru'),
            'statistics' => env('WB_SANDBOX_STATISTICS_API_URL', 'https://statistics-api-sandbox.wildberries.ru'),
        ],
        'timeout' => (int) env('WB_API_TIMEOUT', 20),
        'connect_timeout' => (int) env('WB_API_CONNECT_TIMEOUT', 5),
        'client_secret' => env('WB_CLIENT_SECRET'),
        'products_page_limit' => 100,
        'statistics_page_limit' => 80_000,
        'stocks_page_limit' => 250_000,
        'raw_page_retention_days' => 7,
        'historical_report_max_bytes' => (int) env('WB_HISTORICAL_REPORT_MAX_BYTES', 100_000_000),
    ],

    'synchronization' => [
        'cadence_minutes' => (int) env('SELLERSCOPE_SYNC_CADENCE_MINUTES', 30),
        'stalled_after_minutes' => (int) env('SELLERSCOPE_SYNC_STALLED_AFTER_MINUTES', 90),
        'queue_backlog_alert' => (int) env('SELLERSCOPE_QUEUE_BACKLOG_ALERT', 250),
        'retry_base_seconds' => (int) env('SELLERSCOPE_SYNC_RETRY_BASE_SECONDS', 30),
        'retry_max_seconds' => (int) env('SELLERSCOPE_SYNC_RETRY_MAX_SECONDS', 900),
        'retry_after_max_seconds' => (int) env('SELLERSCOPE_SYNC_RETRY_AFTER_MAX_SECONDS', 86_400),
        'max_pages_per_resource' => (int) env('SELLERSCOPE_SYNC_MAX_PAGES', 10_000),
    ],
];
