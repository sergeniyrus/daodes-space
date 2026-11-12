<?php

namespace Tests\Feature;

use App\Contracts\SmsTransport;
use App\Models\Customer;
use App\Models\CustomerPhoneChallenge;
use App\Services\CustomerPhoneOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CustomerPhoneOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_code_is_hashed_and_can_be_used_only_once(): void
    {
        $plainCode = null;

        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->with(
                '+436601234567',
                Mockery::on(function (string $message) use (&$plainCode): bool {
                    preg_match('/код входа (\d{6})/', $message, $matches);
                    $plainCode = $matches[1] ?? null;

                    return $plainCode !== null;
                })
            )
            ->andReturnNull();

        $service = new CustomerPhoneOtpService($transport);
        $service->requestCode('+436601234567', 'Test customer');

        $customer = Customer::query()
            ->where('phone', '+436601234567')
            ->firstOrFail();

        $challenge = $customer->phoneChallenges()->latest('id')->firstOrFail();

        $this->assertNotNull($plainCode);
        $this->assertSame(64, strlen($challenge->code_hash));
        $this->assertNotSame($plainCode, $challenge->code_hash);
        $this->assertNotNull($challenge->sent_at);

        $verifiedCustomer = $service->verifyCode(
            '+436601234567',
            $plainCode
        );

        $this->assertNotNull($verifiedCustomer);
        $this->assertNotNull($verifiedCustomer->phone_verified_at);
        $this->assertNotNull($challenge->fresh()->consumed_at);

        $this->assertNull(
            $service->verifyCode('+436601234567', $plainCode),
            'A consumed OTP must not be accepted again.'
        );
    }

    public function test_fifth_invalid_attempt_revokes_the_otp(): void
    {
        $plainCode = null;

        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (string $phone, string $message) use (&$plainCode): void {
                preg_match('/код входа (\d{6})/', $message, $matches);
                $plainCode = $matches[1] ?? null;
            });

        $service = new CustomerPhoneOtpService($transport);
        $service->requestCode('+436601234568');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertNull(
                $service->verifyCode('+436601234568', '000000')
            );
        }

        $customer = Customer::query()
            ->where('phone', '+436601234568')
            ->firstOrFail();

        $challenge = $customer->phoneChallenges()->latest('id')->firstOrFail();

        $this->assertSame(5, $challenge->attempts);
        $this->assertNotNull($challenge->revoked_at);

        if ($plainCode !== '000000') {
            $this->assertNull(
                $service->verifyCode('+436601234568', $plainCode)
            );
        }
    }
}
