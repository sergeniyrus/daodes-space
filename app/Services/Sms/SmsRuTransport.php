<?php

namespace App\Services\Sms;

use App\Contracts\SmsTransport;
use App\Exceptions\SmsTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SmsRuTransport implements SmsTransport
{
    private const API_URL = 'https://sms.ru/sms/send';

    public function send(string $phone, string $message): void
    {
        $apiId = (string) config('services.sms.sms_ru.api_id');

        if ($apiId === '') {
            throw new SmsTransportException(
                'Не настроен SMS.RU API ID. Укажите SMS_RU_API_ID в .env.'
            );
        }

        $destination = preg_replace('/\D+/', '', $phone);

        if (!is_string($destination) || $destination === '') {
            throw new SmsTransportException(
                'Не удалось определить номер телефона для отправки SMS.'
            );
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.sms.sms_ru.timeout', 10))
                ->post(self::API_URL, [
                    'api_id' => $apiId,
                    'to' => $destination,
                    'msg' => $message,
                    'json' => 1,
                ]);
        } catch (ConnectionException $exception) {
            throw new SmsTransportException(
                'Не удалось связаться с SMS.RU. Попробуйте позже.',
                previous: $exception
            );
        }

        if (!$response->successful()) {
            throw new SmsTransportException(
                'SMS.RU вернул HTTP-ошибку: ' . $response->status() . '.'
            );
        }

        $result = $response->json();

        if (!is_array($result) || ($result['status'] ?? null) !== 'OK') {
            $providerMessage = is_array($result)
                ? (string) ($result['status_text'] ?? 'Неизвестная ошибка провайдера.')
                : 'Провайдер вернул некорректный ответ.';

            throw new SmsTransportException(
                'SMS.RU не принял SMS: ' . $providerMessage
            );
        }

        $smsResult = $result['sms'][$destination] ?? null;

        if (
            !is_array($smsResult) ||
            ($smsResult['status'] ?? null) !== 'OK' ||
            (int) ($smsResult['status_code'] ?? 0) !== 100
        ) {
            $providerMessage = is_array($smsResult)
                ? (string) ($smsResult['status_text'] ?? 'SMS не принято к отправке.')
                : 'SMS.RU не вернул результат для номера получателя.';

            throw new SmsTransportException(
                'SMS.RU не принял SMS: ' . $providerMessage
            );
        }
    }
}
