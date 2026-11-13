<?php
namespace Tests\Unit;
use App\Exceptions\FirebaseMessagingException;
use App\Services\FirebaseMessagingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class FirebaseMessagingServiceTest extends TestCase
{
    private string $privateKey;
    protected function setUp(): void
    {
        parent::setUp();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $this->privateKey);
        config()->set('services.firebase.project_id', 'daodes-test');
        config()->set('services.firebase.client_email', 'firebase@daodes-test.iam.gserviceaccount.com');
        config()->set('services.firebase.private_key', $this->privateKey);
        config()->set('services.firebase.token_uri', 'https://oauth2.googleapis.com/token');
        Cache::forget('firebase_fcm_access_token_' . hash('sha256', 'firebase@daodes-test.iam.gserviceaccount.com'));
    }
    public function test_it_sends_a_push_message_using_firebase_http_v1(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'firebase-test-access-token', 'expires_in' => 3600]),
            'https://fcm.googleapis.com/v1/projects/daodes-test/messages:send' => Http::response(['name' => 'projects/daodes-test/messages/message-id']),
        ]);
        app(FirebaseMessagingService::class)->send(
            'device-registration-token', 'Заказ готов', 'Заказ №123 готов к выдаче.',
            ['url' => 'https://daodes.space/customer/orders/123', 'order_id' => '123']
        );
        Http::assertSent(function (Request $request): bool {
            if (!str_contains($request->url(), 'messages:send')) {
                return false;
            }
            $message = $request->data()['message'] ?? [];
            return ($message['token'] ?? null) === 'device-registration-token'
                && ($message['notification']['title'] ?? null) === 'Заказ готов'
                && ($message['data']['order_id'] ?? null) === '123'
                && ($message['webpush']['fcm_options']['link'] ?? null) === 'https://daodes.space/customer/orders/123';
        });
    }
    public function test_it_marks_unregistered_device_tokens_as_invalid(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'firebase-test-access-token', 'expires_in' => 3600]),
            'https://fcm.googleapis.com/v1/projects/daodes-test/messages:send' => Http::response([
                'error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]],
            ], 404),
        ]);
        try {
            app(FirebaseMessagingService::class)->send('expired-device-token', 'Заказ готов', 'Заказ готов.', []);
            $this->fail('Expected FirebaseMessagingException was not thrown.');
        } catch (FirebaseMessagingException $exception) {
            $this->assertTrue($exception->isUnregisteredToken());
        }
    }
}
