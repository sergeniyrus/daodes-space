<?php

namespace Tests\Support;

use App\Contracts\PaymentGateway;
use App\Data\Payments\PaymentCheckoutSession;
use App\Data\Payments\PaymentWebhookEvent;
use App\Exceptions\InvalidPaymentWebhookException;

class FakePaymentGateway implements PaymentGateway
{
    public function createCheckout(
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): PaymentCheckoutSession {
        return new PaymentCheckoutSession(
            'fake_' . $idempotencyKey,
            'https://payments.invalid/checkout/' . rawurlencode($idempotencyKey)
        );
    }

    public function verifyWebhook(string $payload, array $headers): PaymentWebhookEvent
    {
        $signature = $headers['x-fake-signature'][0] ?? '';
        $expected = hash_hmac(
            'sha256',
            $payload,
            (string) config('payments.testing_secret')
        );

        if (!is_string($signature) || !hash_equals($expected, $signature)) {
            throw new InvalidPaymentWebhookException('Invalid test signature.');
        }

        $data = json_decode($payload, true);

        if (
            !is_array($data)
            || !isset(
                $data['event_id'],
                $data['payment_id'],
                $data['status'],
                $data['amount'],
                $data['currency']
            )
        ) {
            throw new InvalidPaymentWebhookException('Invalid test payload.');
        }

        return new PaymentWebhookEvent(
            'fake',
            (string) $data['event_id'],
            (string) $data['payment_id'],
            (string) $data['status'],
            (string) $data['amount'],
            (string) $data['currency']
        );
    }
}
