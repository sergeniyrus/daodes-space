<?php

namespace App\Services;

use App\Contracts\SmsTransport;
use App\Exceptions\CustomerOtpRateLimitException;
use App\Exceptions\SmsTransportException;
use App\Models\Customer;
use App\Models\OrganizationEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AccountPasswordRecoveryService
{
    private const CODE_TTL_MINUTES = 10;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_SENDS_PER_HOUR = 3;
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(private readonly SmsTransport $smsTransport) {}

    public function request(string $accountType, string $phone, ?string $name = null): void
    {
        if (!in_array($accountType, ['customer', 'employee'], true)) {
            throw new RuntimeException('Unsupported account type.');
        }

        $payload = DB::transaction(function () use ($accountType, $phone, $name) {
            if ($accountType === 'customer') {
                $account = Customer::query()->where('phone', $phone)->lockForUpdate()->first();
                if (!$account) {
                    $account = Customer::query()->create(['phone' => $phone, 'name' => $name]);
                } elseif (!$account->name && $name) {
                    $account->update(['name' => $name]);
                }
            } else {
                $account = OrganizationEmployee::query()->where('phone', $phone)->lockForUpdate()->first();
                if (!$account || !$account->phone_verified_at || !$account->activeMemberships()->exists()) {
                    return null;
                }
            }

            $now = now();
            $recent = DB::table('account_recovery_challenges')
                ->where('account_type', $accountType)
                ->where('account_id', $account->id)
                ->where('created_at', '>=', $now->copy()->subHour())
                ->orderByDesc('id')->lockForUpdate()->get();

            if ($recent->count() >= self::MAX_SENDS_PER_HOUR) {
                throw new CustomerOtpRateLimitException('Превышен лимит SMS-кодов. Повторите попытку позднее.');
            }

            $last = $recent->first();
            if ($last && \Illuminate\Support\Carbon::parse($last->created_at)->gt($now->copy()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
                throw new CustomerOtpRateLimitException('Повторная отправка кода доступна через минуту.');
            }

            DB::table('account_recovery_challenges')
                ->where('account_type', $accountType)->where('account_id', $account->id)
                ->whereNull('consumed_at')->whereNull('revoked_at')
                ->update(['revoked_at' => $now, 'updated_at' => $now]);

            $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $id = DB::table('account_recovery_challenges')->insertGetId([
                'account_type' => $accountType,
                'account_id' => $account->id,
                'phone' => $phone,
                'code_hash' => str_repeat('0', 64),
                'attempts' => 0,
                'expires_at' => $now->copy()->addMinutes(self::CODE_TTL_MINUTES),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('account_recovery_challenges')->where('id', $id)->update([
                'code_hash' => $this->codeHash($plainCode, (string) $id),
                'updated_at' => $now,
            ]);

            return ['id' => $id, 'phone' => $phone, 'code' => $plainCode];
        });

        if (!$payload) {
            return;
        }

        try {
            $this->smsTransport->send(
                $payload['phone'],
                'DAODES: код подтверждения ' . $payload['code'] . '. Действует 10 минут. Никому не сообщайте этот код.'
            );
        } catch (Throwable $exception) {
            DB::table('account_recovery_challenges')->where('id', $payload['id'])
                ->whereNull('consumed_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
            if ($exception instanceof SmsTransportException) {
                throw $exception;
            }
            throw new SmsTransportException('Не удалось передать SMS провайдеру. Код аннулирован.', previous: $exception);
        }

        DB::table('account_recovery_challenges')->where('id', $payload['id'])
            ->whereNull('consumed_at')->whereNull('revoked_at')
            ->update(['sent_at' => now(), 'updated_at' => now()]);
    }

    public function reset(string $accountType, string $phone, string $code, string $password): bool
    {
        if (!in_array($accountType, ['customer', 'employee'], true) || preg_match('/\A[0-9]{6}\z/', $code) !== 1) {
            return false;
        }

        return DB::transaction(function () use ($accountType, $phone, $code, $password) {
            if ($accountType === 'customer') {
                $account = Customer::query()->where('phone', $phone)->lockForUpdate()->first();
            } else {
                $account = OrganizationEmployee::query()->where('phone', $phone)->lockForUpdate()->first();
                if (!$account || !$account->phone_verified_at || !$account->activeMemberships()->exists()) {
                    return false;
                }
            }
            if (!$account) {
                return false;
            }

            $challenge = DB::table('account_recovery_challenges')
                ->where('account_type', $accountType)->where('account_id', $account->id)
                ->where('phone', $phone)->whereNotNull('sent_at')
                ->whereNull('consumed_at')->whereNull('revoked_at')
                ->orderByDesc('id')->lockForUpdate()->first();

            if (!$challenge) {
                return false;
            }

            if (\Illuminate\Support\Carbon::parse($challenge->expires_at)->isPast() || $challenge->attempts >= self::MAX_CODE_ATTEMPTS) {
                DB::table('account_recovery_challenges')->where('id', $challenge->id)
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);
                return false;
            }

            $attempts = $challenge->attempts + 1;
            $valid = hash_equals($challenge->code_hash, $this->codeHash($code, (string) $challenge->id));

            if (!$valid) {
                DB::table('account_recovery_challenges')->where('id', $challenge->id)->update([
                    'attempts' => $attempts,
                    'revoked_at' => $attempts >= self::MAX_CODE_ATTEMPTS ? now() : null,
                    'updated_at' => now(),
                ]);
                return false;
            }

            DB::table('account_recovery_challenges')->where('id', $challenge->id)->update([
                'attempts' => $attempts, 'consumed_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('account_recovery_challenges')
                ->where('account_type', $accountType)->where('account_id', $account->id)
                ->where('id', '!=', $challenge->id)->whereNull('consumed_at')->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            $account->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ]);
            if ($accountType === 'customer' && !$account->phone_verified_at) {
                $account->phone_verified_at = now();
            }
            $account->save();

            return true;
        });
    }

    private function codeHash(string $code, string $challengeId): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for account recovery.');
        }
        return hash_hmac('sha256', $challengeId . '|' . $code, $key);
    }
}