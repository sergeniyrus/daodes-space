<?php

namespace App\Data\Payments;

final readonly class PaymentCheckoutSession
{
    public function __construct(
        public string $providerPaymentId,
        public string $redirectUrl,
    ) {
    }
}
