<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment provider
    |--------------------------------------------------------------------------
    |
    | Провайдеры подключаются отдельными адаптерами, реализующими
    | App\Contracts\PaymentGateway. Пустой список означает, что онлайн-
    | платежи и webhook-приём отключены.
    |
    */

    'default' => env('PAYMENT_PROVIDER'),

    'providers' => [
        // 'provider_key' => App\Services\Payments\Providers\ProviderGateway::class,
    ],
];
