<?php

namespace App\Services\Sms;

use App\Contracts\SmsTransport;
use App\Exceptions\SmsProviderNotConfiguredException;

class UnconfiguredSmsTransport implements SmsTransport
{
    /**
     * Намеренно ничего не отправляет.
     *
     * Это безопасная заглушка до подключения выбранного SMS-провайдера:
     * код не выдаётся в интерфейс, логи или ответ API.
     */
    public function send(string $phone, string $message): void
    {
        throw new SmsProviderNotConfiguredException();
    }
}
