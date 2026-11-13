<?php

namespace Tests\Unit;

use App\Exceptions\SmsTransportException;
use App\Services\Sms\SmsRuTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsRuTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms.sms_ru.api_id' => 'test-api-id',
            'services.sms.sms_ru.timeout' => 10,
        ]);
    }

    public function test_it_sends_sms_using_sms_ru_form_api(): void
    {
        Http::fake([
            'https://sms.ru/sms/send' => Http::response([
                'status' => 'OK',
                'status_code' => 100,
                'sms' => [
                    '79991234567' => [
                        'status' => 'OK',
                        'status_code' => 100,
                        'sms_id' => 'test-message-id',
                    ],
                ],
            ], 200),
        ]);

        $transport = new SmsRuTransport();

        $transport->send(
            '+7 (999) 123-45-67',
            'DAODES: код входа 123456.'
        );

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://sms.ru/sms/send'
                && $request['api_id'] === 'test-api-id'
                && $request['to'] === '79991234567'
                && $request['msg'] === 'DAODES: код входа 123456.'
                && (string) $request['json'] === '1';
        });
    }

    public function test_it_throws_when_sms_ru_rejects_the_message(): void
    {
        Http::fake([
            'https://sms.ru/sms/send' => Http::response([
                'status' => 'OK',
                'status_code' => 100,
                'sms' => [
                    '79991234567' => [
                        'status' => 'ERROR',
                        'status_code' => 207,
                        'status_text' => 'No delivery route',
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(SmsTransportException::class);

        (new SmsRuTransport())->send(
            '+79991234567',
            'DAODES: код входа 123456.'
        );
    }

    public function test_it_does_not_send_when_api_id_is_missing(): void
    {
        config(['services.sms.sms_ru.api_id' => null]);

        Http::fake();

        $this->expectException(SmsTransportException::class);

        (new SmsRuTransport())->send(
            '+79991234567',
            'DAODES: код входа 123456.'
        );
    }
}
