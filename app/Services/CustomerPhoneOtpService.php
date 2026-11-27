<?php

namespace App\Services;

use App\Contracts\SmsTransport;
use App\Exceptions\CustomerOtpRateLimitException;
use App\Exceptions\SmsTransportException;
use App\Models\Customer;
use App\Models\CustomerPhoneChallenge;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CustomerPhoneOtpService
{
    private const CODE_TTL_MINUTES = 10;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_SENDS_PER_HOUR = 3;
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(
        private readonly SmsTransport $smsTransport
    ) {
    }

    /**
     * Создаёт или находит клиента по телефону и отправляет одноразовый код.
     * В базе хранится только HMAC-хеш кода.
     */
    public function requestCode(string $phone, ?string $name = null): void
    {
        $payload = DB::transaction(function () use ($phone, $name) {
            $customer = Customer::query()
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if (!$customer) {
                $customer = Customer::create([
                    'phone' => $phone,
                    'name' => $name,
                ]);
            } elseif (!$customer->name && $name) {
                $customer->update(['name' => $name]);
            }

            $now = now();

            $recentChallenges = $customer->phoneChallenges()
                ->where('created_at', '>=', $now->copy()->subHour())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($recentChallenges->count() >= self::MAX_SENDS_PER_HOUR) {
                throw new CustomerOtpRateLimitException(
                    'Превышен лимит SMS-кодов. Повторите попытку позднее.'
                );
            }

            $lastChallenge = $recentChallenges->first();

            if (
                $lastChallenge &&
                $lastChallenge->created_at->gt(
                    $now->copy()->subSeconds(self::RESEND_COOLDOWN_SECONDS)
                )
            ) {
                throw new CustomerOtpRateLimitException(
                    'Повторная отправка кода доступна через минуту.'
                );
            }

            $customer->phoneChallenges()
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);

            $plainCode = str_pad(
                (string) random_int(0, 999999),
                6,
                '0',
                STR_PAD_LEFT
            );

            $challenge = $customer->phoneChallenges()->create([
                'code_hash' => str_repeat('0', 64),
                'attempts' => 0,
                'expires_at' => $now->copy()->addMinutes(self::CODE_TTL_MINUTES),
            ]);

            $challenge->forceFill([
                'code_hash' => $this->codeHash($plainCode, (string) $challenge->id),
            ])->save();

            return [
                'challenge_id' => $challenge->id,
                'phone' => $customer->phone,
                'code' => $plainCode,
            ];
        });

        try {
            $this->smsTransport->send(
                $payload['phone'],
                'DAODES: код входа ' . $payload['code'] .
                    '. Действует 10 минут. Никому не сообщайте этот код.'
            );
        } catch (Throwable $exception) {
            CustomerPhoneChallenge::query()
                ->whereKey($payload['challenge_id'])
                ->whereNull('consumed_at')
                ->update(['revoked_at' => now()]);

            if ($exception instanceof SmsTransportException) {
                throw $exception;
            }

            throw new SmsTransportException(
                'Не удалось передать SMS провайдеру. Код аннулирован.',
                previous: $exception
            );
        }

        CustomerPhoneChallenge::query()
            ->whereKey($payload['challenge_id'])
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['sent_at' => now()]);
    }

    /**
     * Проверяет OTP. Один код можно успешно применить только один раз.
     */
    public function verifyCode(string $phone, string $code): ?Customer
    {
        return DB::transaction(function () use ($phone, $code) {
            $customer = Customer::query()
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if (!$customer) {
                return null;
            }

            $challenge = $customer->phoneChallenges()
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$challenge || !$challenge->sent_at) {
                return null;
            }

            if (
                $challenge->expires_at->isPast() ||
                $challenge->attempts >= self::MAX_CODE_ATTEMPTS
            ) {
                $challenge->forceFill(['revoked_at' => now()])->save();

                return null;
            }

            $challenge->attempts++;
            $expectedHash = $this->codeHash($code, (string) $challenge->id);

            if (!hash_equals($challenge->code_hash, $expectedHash)) {
                if ($challenge->attempts >= self::MAX_CODE_ATTEMPTS) {
                    $challenge->revoked_at = now();
                }

                $challenge->save();

                return null;
            }

            $challenge->forceFill([
                'consumed_at' => now(),
            ])->save();

            $customer->phoneChallenges()
                ->where('id', '!=', $challenge->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            if (!$customer->phone_verified_at) {
                $customer->forceFill([
                    'phone_verified_at' => now(),
                ])->save();
            }

            return $customer;
        });
    }

    private function codeHash(string $code, string $challengeId): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for customer phone verification.');
        }

        return hash_hmac(
            'sha256',
            $challengeId . '|' . $code,
            $key
        );
    }
}
