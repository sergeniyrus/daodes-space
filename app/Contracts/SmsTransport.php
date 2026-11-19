<?php

namespace App\Contracts;

interface SmsTransport
{
    /**
     * Provider-neutral boundary for outbound SMS.
     *
     * The phone number is supplied in E.164 format. Implementations must throw
     * SmsTransportException when the provider rejects or cannot accept a message.
     * Never write message contents or one-time codes to application logs.
     *
     * Production integration point: AppServiceProvider::register().
     */
    public function send(string $phone, string $message): void;
}
