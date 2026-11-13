<?php

namespace Tests\Feature;

use App\Contracts\SmsTransport;
use App\Models\Customer;
use App\Services\AccountPasswordRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AccountPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_confirm_phone_and_set_a_password_once(): void
    {
        $phone = '+436601234567';
        $password = 'Secure-Customer-Pass-2026!';
        $plainCode = null;

        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->with(
                $phone,
                Mockery::on(function (string $message) use (&$plainCode): bool {
                    preg_match('/код подтверждения ([0-9]{6})/', $message, $matches);
                    $plainCode = $matches[1] ?? null;

                    return $plainCode !== null && str_contains($message, 'Действует 10 минут');
                })
            )
            ->andReturnNull();

        $service = new AccountPasswordRecoveryService($transport);
        $service->request('customer', $phone, 'Test customer');

        $challenge = DB::table('account_recovery_challenges')->first();

        $this->assertNotNull($plainCode);
        $this->assertNotSame($plainCode, $challenge->code_hash);
        $this->assertSame(64, strlen($challenge->code_hash));
        $this->assertNotNull($challenge->sent_at);

        $this->assertTrue($service->reset('customer', $phone, $plainCode, $password));

        $customer = Customer::query()->where('phone', $phone)->firstOrFail();

        $this->assertTrue(Hash::check($password, $customer->password));
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertNotNull(DB::table('account_recovery_challenges')->value('consumed_at'));

        $this->assertFalse(
            $service->reset('customer', $phone, $plainCode, 'Another-Password-2026!'),
            'A consumed recovery code must never be accepted twice.'
        );
    }

    public function test_fifth_invalid_recovery_code_attempt_revokes_the_challenge(): void
    {
        $phone = '+436601234568';
        $plainCode = null;

        $transport = Mockery::mock(SmsTransport::class);
        $transport->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (string $sentPhone, string $message) use (&$plainCode): void {
                preg_match('/код подтверждения ([0-9]{6})/', $message, $matches);
                $plainCode = $matches[1] ?? null;
            });

        $service = new AccountPasswordRecoveryService($transport);
        $service->request('customer', $phone, 'Attempt test');

        $wrongCode = $plainCode === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertFalse(
                $service->reset('customer', $phone, $wrongCode, 'Secure-Customer-Pass-2026!')
            );
        }

        $challenge = DB::table('account_recovery_challenges')->first();

        $this->assertSame(5, (int) $challenge->attempts);
        $this->assertNotNull($challenge->revoked_at);
        $this->assertNull(Customer::query()->where('phone', $phone)->firstOrFail()->password);
        $this->assertFalse($service->reset('customer', $phone, $plainCode, 'Secure-Customer-Pass-2026!'));
    }
}