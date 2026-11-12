<?php

namespace App\Contracts;

interface SmsTransport
{
    /**
     * Отправляет SMS через подключённого провайдера.
     *
     * Реализация обязана выбрасывать SmsTransportException при ошибке.
     */
    public function send(string $phone, string $message): void;
}
