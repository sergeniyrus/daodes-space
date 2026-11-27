<?php

namespace App\Contracts;

use App\Data\Payments\PaymentCheckoutSession;
use App\Data\Payments\PaymentWebhookEvent;

interface PaymentGateway
{
    /**
     * Создаёт платёжную сессию у провайдера.
     * Реализация должна использовать сохранённый локальный payment idempotency key.
     */
    public function createCheckout(
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): PaymentCheckoutSession;

    /**
     * Проверяет подпись и разбирает исходное уведомление провайдера.
     * Нельзя доверять данным до успешной проверки подписи.
     */
    public function verifyWebhook(string $payload, array $headers): PaymentWebhookEvent;
}
