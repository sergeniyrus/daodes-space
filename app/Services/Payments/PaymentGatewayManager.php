<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentProviderUnavailableException;
use LogicException;

class PaymentGatewayManager
{
    public function gateway(string $provider): PaymentGateway
    {
        $gatewayClass = config("payments.providers.{$provider}");

        if (!is_string($gatewayClass) || $gatewayClass === '') {
            throw new PaymentProviderUnavailableException(
                'Платёжный провайдер не настроен.'
            );
        }

        $gateway = app($gatewayClass);

        if (!$gateway instanceof PaymentGateway) {
            throw new LogicException(
                "Payment gateway [{$provider}] must implement " . PaymentGateway::class
            );
        }

        return $gateway;
    }
}
