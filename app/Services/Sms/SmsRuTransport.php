<?php

namespace App\Services\Sms;

use App\Contracts\SmsTransport;
use App\Exceptions\SmsProviderNotConfiguredException;
use App\Exceptions\SmsTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SmsRuTransport implements SmsTransport
{
    private const API_URL = 'https://sms.ru/sms/send';

    public function send(string $phone, string $message): void
    {
        $apiId = config('services.sms_ru.api_id');

        if (!is_string($apiId) || trim($apiId) === '') {
            throw new SmsProviderNotConfiguredException();
        }

        $recipient = preg_replace('/\D+/', '', $phone);

        if (!is_string($recipient) || strlen($recipient) < 8 || strlen($recipient) > 15) {
            throw new SmsTransportException('Неверный формат номера телефона для отправки SMS.');
        }

        $payload = [
            'api_id' => $apiId,
            'to' => $recipient,
            'msg' => $message,
            'json' => 1,
        ];

        $sender = config('services.sms_ru.from');
        if (is_string($sender) && trim($sender) !== '') {
            $payload['from'] = trim($sender);
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->post(self::API_URL, $payload);
        } catch (ConnectionException $exception) {
            throw new SmsTransportException(
                'Сервис SMS.RU временно недоступен. Попробуйте позднее.',
                previous: $exception
            );
        }

        if (!$response->successful()) {
            throw new SmsTransportException(
                'SMS.RU вернул HTTP-ошибку (' . $response->status() . ').'
            );
        }

        $result = $response->json();

        if (!is_array($result)) {
            throw new SmsTransportException('SMS.RU вернул некорректный ответ.');
        }

        if (($result['status'] ?? null) !== 'OK') {
            $code = isset($result['status_code']) ? (string) $result['status_code'] : 'unknown';

            throw new SmsTransportException(
                'SMS.RU отклонил запрос (код ' . $code . '). Проверьте настройки аккаунта и отправителя.'
            );
        }

        $smsResult = $result['sms'][$recipient] ?? null;

        if (!is_array($smsResult) || ($smsResult['status'] ?? null) !== 'OK') {
            $code = isset($smsResult['status_code']) ? (string) $smsResult['status_code'] : 'unknown';

            throw new SmsTransportException(
                'SMS.RU не принял сообщение на указанный номер (код ' . $code . ').'
            );
        }
    }
}
