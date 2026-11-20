<?php

return [
    /*
    |--------------------------------------------------------------------------
    | iikoCloud API
    |--------------------------------------------------------------------------
    |
    | appId and clientSecret belong to the DAODES integration application.
    | Each organization stores its own encrypted iiko API key.
    |
    */

    'base_url' => env('IIKO_BASE_URL', 'https://api-ru.iiko.services'),
    'app_id' => env('IIKO_APP_ID'),
    'client_secret' => env('IIKO_CLIENT_SECRET'),
    'source_key' => env('IIKO_SOURCE_KEY'),
    'webhook_url' => env('IIKO_WEBHOOK_URL', 'https://daodes.space/api/integrations/iiko/webhook'),
    'timeout' => (int) env('IIKO_TIMEOUT', 20),
    'connect_timeout' => (int) env('IIKO_CONNECT_TIMEOUT', 5),

    // iiko JWT tokens live for one hour; cache for 55 minutes.
    'token_cache_minutes' => (int) env('IIKO_TOKEN_CACHE_MINUTES', 55),
];
