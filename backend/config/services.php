<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

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

    'exchange' => [
        'base_url' => env('EXCHANGE_BASE_URL', 'https://api.frankfurter.dev/v1'),
        'intraday_url' => env('EXCHANGE_INTRADAY_URL', 'https://query1.finance.yahoo.com/v8/finance/chart'),
        'base' => env('EXCHANGE_BASE_CURRENCY', 'EUR'),
        'symbols' => array_filter(array_map(
            'trim',
            explode(',', env('EXCHANGE_SYMBOLS', 'USD,GBP,JPY,CHF,PLN'))
        )),
        'cache_ttl' => env('EXCHANGE_CACHE_TTL', 60),
        'timeout' => env('EXCHANGE_TIMEOUT', 5),
        'stream_interval' => env('EXCHANGE_STREAM_INTERVAL', 10),
    ],

];
