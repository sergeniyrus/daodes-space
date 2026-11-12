<?php

namespace App\Exceptions;

class SmsProviderNotConfiguredException extends SmsTransportException
{
    public function __construct()
    {
        parent::__construct(
            'SMS-провайдер не подключён. Настройте реализацию SmsTransport перед отправкой приглашений.'
        );
    }
}
