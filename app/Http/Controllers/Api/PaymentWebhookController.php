<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidPaymentWebhookException;
use App\Exceptions\PaymentProviderUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        PaymentGatewayManager $gateways,
        PaymentWebhookProcessor $processor
    ): JsonResponse {
        $payload = $request->getContent();

        if ($payload === '' || strlen($payload) > 65536) {
            return response()->json([
                'message' => 'Некорректный размер webhook payload.',
            ], 400);
        }

        try {
            $gateway = $gateways->gateway($provider);
            $event = $gateway->verifyWebhook($payload, $request->headers->all());
            $result = $processor->process($provider, $event, $payload);

            return response()->json([
                'received' => true,
                'duplicate' => $result['duplicate'],
                'processing_status' => $result['status'],
            ]);
        } catch (PaymentProviderUnavailableException $exception) {
            return response()->json([
                'message' => 'Платёжный провайдер не настроен.',
            ], 503);
        } catch (InvalidPaymentWebhookException|InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'Подпись или содержимое webhook не прошли проверку.',
            ], 400);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось обработать платёжное уведомление.',
            ], 500);
        }
    }
}
