<?php

return [
    'postmark' => ['key' => env('POSTMARK_API_KEY')],
    'resend' => ['key' => env('RESEND_API_KEY')],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'coinmarketcap' => [
        'api_key' => env('CMC_API_KEY'),
        'base_url' => env('CMC_BASE_URL', 'https://pro-api.coinmarketcap.com'),
        'timeout' => (int) env('CMC_HTTP_TIMEOUT', 30),
    ],
    'nobitex' => [
        'base_url' => env('NOBITEX_BASE_URL', 'https://apiv2.nobitex.ir'),
        'websocket_url' => env('NOBITEX_WS_URL', 'wss://ws.nobitex.ir/connection/websocket'),
        'timeout' => (int) env('NOBITEX_HTTP_TIMEOUT', 15),
    ],
];
