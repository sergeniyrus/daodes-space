<?php

namespace Tests\Feature;

use App\Exceptions\SmsProviderNotConfiguredException;
use App\Exceptions\SmsTransportException;
use App\Services\Sms\SmsRuTransport;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsRuTransportTest extends TestCase
{
    public function test_it_sends_a_form_encoded_sms_and_accepts_provider_confirmation(): void
    {
        config(['services.sms_ru.api_id' => 'test-api-id']);
        config(['services.sms_ru.from' => null]);

        Http::fake([
            'sms.ru/sms/send' => Http::response([
                'status' => 'OK',
                'status_code' => 100,
                'sms' => [
                    '436601234567' => [
                        'status' => 'OK',
                        'status_code' => 100,
                        'sms_id' => 'test-message-id',
                    ],
                ],
            ], 200),
        ]);

        app(SmsRuTransport::class)->send(
            '+436601234567',
            'DAODES: код подтверждения 123456.'
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://sms.ru/sms/send'
                && $request->method() === 'POST'
                && $request['api_id'] === 'test-api-id'
                && $request['to'] === '436601234567'
                && $request['msg'] === 'DAODES: код подтверждения 123456.'
                && (string) $request['json'] === '1';
        });
    }

    public function test_it_uses_an_approved_sender_when_configured(): void
    {
        config([
            'services.sms_ru.api_id' => 'test-api-id',
            'services.sms_ru.from' => 'DAODES',
        ]);

        Http::fake([
            'sms.ru/sms/send' => Http::response([
                'status' => 'OK',
                'status_code' => 100,
                'sms' => [
                    '79991234567' => ['status' => 'OK', 'status_code' => 100],
                ],
            ], 200),
        ]);

        app(SmsRuTransport::class)->send('+79991234567', 'Test message');

        Http::assertSent(fn ($request): bool => $request['from'] === 'DAODES');
    }

    public function test_it_rejects_a_message_when_sms_ru_rejects_the_recipient(): void
    {
        config(['services.sms_ru.api_id' => 'test-api-id']);

        Http::fake([
            'sms.ru/sms/send' => Http::response([
                'status' => 'OK',
                'status_code' => 100,
                'sms' => [
                    '79991234567' => [
                        'status' => 'ERROR',
                        'status_code' => 207,
                        'status_text' => 'No route',
                    ],
                ],
            ], 200),
        ]);

        try {
            app(SmsRuTransport::class)->send('+79991234567', 'Test message');
            $this->fail('A provider rejection must raise SmsTransportException.');
        } catch (SmsTransportException $exception) {
            $this->assertStringContainsString('207', $exception->getMessage());
        }
    }

    public function test_it_does_not_send_when_api_id_is_missing(): void
    {
        config(['services.sms_ru.api_id' => null]);

        $this->expectException(SmsProviderNotConfiguredException::class);

        app(SmsRuTransport::class)->send('+79991234567', 'Test message');
    }
}
