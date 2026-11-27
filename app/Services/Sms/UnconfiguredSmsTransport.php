<?php

namespace App\Services\Sms;

use App\Contracts\SmsTransport;
use App\Exceptions\SmsProviderNotConfiguredException;

class UnconfiguredSmsTransport implements SmsTransport
{
    /**
     * Намеренно ничего не отправляет.
     *
     * Это безопасная реализация до выбора и подключения SMS-провайдера:
     * она ничего не отправляет и не раскрывает код в интерфейсе, логах или API.
     */
    public function send(string $phone, string $message): void
    {
        throw new SmsProviderNotConfiguredException();
    }
}
