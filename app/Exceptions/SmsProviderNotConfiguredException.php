<?php

namespace App\Exceptions;

class SmsProviderNotConfiguredException extends SmsTransportException
{
    public function __construct()
    {
        parent::__construct(
            'SMS-провайдер не выбран и не подключён. Реализуйте SmsTransport и замените привязку в AppServiceProvider.'
        );
    }
}
