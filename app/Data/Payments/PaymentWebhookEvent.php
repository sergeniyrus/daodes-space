<?php

namespace App\Data\Payments;

final readonly class PaymentWebhookEvent
{
    public function __construct(
        public string $provider,
        public string $eventId,
        public string $providerPaymentId,
        public string $status,
        public string $amount,
        public string $currency,
    ) {
    }
}
