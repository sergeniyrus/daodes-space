<?php

return [
    // Laravel 11 использует BROADCAST_CONNECTION; сохраняем поддержку старого BROADCAST_DRIVER.
    // Без установленных Reverb-зависимостей безопасный fallback — log.
    'default' => env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', 'log')),

    'connections' => [
        // 'ably' => [
        //     'driver' => 'ably',
        //     'key' => env('ABLY_KEY'),
        // ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8090),
                'scheme' => env('REVERB_SCHEME', 'http'),
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
