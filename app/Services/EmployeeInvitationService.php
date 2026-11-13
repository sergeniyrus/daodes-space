<?php

namespace App\Services;

use App\Contracts\SmsTransport;
use App\Exceptions\EmployeeInvitationRateLimitException;
use App\Exceptions\SmsTransportException;
use App\Models\EmployeePhoneChallenge;
use App\Models\OrganizationEmployee;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class EmployeeInvitationService
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
     * Создаёт одноразовый код и передаёт его только SMS-адаптеру.
     * В БД сохраняется исключительно HMAC-хеш кода.
     *
     * Ограничение отправки считается на уровне общей учётной записи телефона,
     * а повторная отправка отзывает только приглашение этой организации.
     */
    public function sendInvitation(
        int $employeeId,
        int $organizationId
    ): void {
        $payload = DB::transaction(function () use ($employeeId, $organizationId) {
            $employee = OrganizationEmployee::query()
                ->whereKey($employeeId)
                ->lockForUpdate()
                ->firstOrFail();

            $membership = $employee->memberships()
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($employee->phone_verified_at) {
                throw new EmployeeInvitationRateLimitException(
                    'Телефон уже подтверждён. Повторное приглашение для смены пароля не допускается.'
                );
            }

            if ($membership->status === 'blocked') {
                throw new EmployeeInvitationRateLimitException(
                    'Нельзя отправить приглашение заблокированному членству.'
                );
            }

            $now = now();

            $recentChallenges = EmployeePhoneChallenge::query()
                ->where('employee_id', $employee->id)
                ->where('created_at', '>=', $now->copy()->subHour())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($recentChallenges->count() >= self::MAX_SENDS_PER_HOUR) {
                throw new EmployeeInvitationRateLimitException(
                    'Превышен лимит приглашений. Повторите попытку позднее.'
                );
            }

            $lastChallenge = $recentChallenges->first();

            if (
                $lastChallenge &&
                $lastChallenge->created_at->gt(
                    $now->copy()->subSeconds(self::RESEND_COOLDOWN_SECONDS)
                )
            ) {
                throw new EmployeeInvitationRateLimitException(
                    'Повторная отправка доступна через минуту.'
                );
            }

            // Приглашения разных организаций не должны отзывать друг друга.
            EmployeePhoneChallenge::query()
                ->where('employee_id', $employee->id)
                ->where('organization_id', $organizationId)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);

            $plainCode = str_pad(
                (string) random_int(0, 999999),
                6,
                '0',
                STR_PAD_LEFT
            );

            $challenge = EmployeePhoneChallenge::create([
                'employee_id' => $employee->id,
                'organization_id' => $organizationId,
                'code_hash' => str_repeat('0', 64),
                'attempts' => 0,
                'expires_at' => $now->copy()->addMinutes(self::CODE_TTL_MINUTES),
            ]);

            $challenge->forceFill([
                'code_hash' => $this->codeHash($plainCode, (string) $challenge->id),
            ])->save();

            return [
                'challenge_id' => $challenge->id,
                'phone' => $employee->phone,
                'code' => $plainCode,
            ];
        });

        try {
            $this->smsTransport->send(
                $payload['phone'],
                'DAODES: код подтверждения телефона ' . $payload['code'] .
                    '. Действует 10 минут. Никому не сообщайте этот код.'
            );
        } catch (Throwable $exception) {
            EmployeePhoneChallenge::query()
                ->whereKey($payload['challenge_id'])
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            if ($exception instanceof SmsTransportException) {
                throw $exception;
            }

            throw new SmsTransportException(
                'Не удалось передать SMS провайдеру. Код аннулирован; повторите отправку позднее.',
                previous: $exception
            );
        }

        EmployeePhoneChallenge::query()
            ->whereKey($payload['challenge_id'])
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['sent_at' => now()]);
    }

    /**
     * Проверяет код и устанавливает пароль сотрудника в одной транзакции.
     * Код может относиться к любому ещё действующему приглашению сотрудника.
     * После успешной активации остальные приглашения общей учётной записи
     * отзываются, чтобы телефон и пароль активировались только один раз.
     */
    public function activate(
        string $phone,
        string $code,
        string $password
    ): bool {
        if (preg_match('/\A[0-9]{6}\z/', $code) !== 1) {
            return false;
        }

        return DB::transaction(function () use ($phone, $code, $password) {
            $employee = OrganizationEmployee::query()
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if (!$employee || $employee->phone_verified_at) {
                return false;
            }

            $challenges = EmployeePhoneChallenge::query()
                ->where('employee_id', $employee->id)
                ->whereNotNull('sent_at')
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($challenges->isEmpty()) {
                return false;
            }

            $matchedChallenge = null;

            foreach ($challenges as $challenge) {
                if (
                    $challenge->expires_at->isPast() ||
                    $challenge->attempts >= self::MAX_CODE_ATTEMPTS
                ) {
                    $challenge->forceFill([
                        'revoked_at' => now(),
                    ])->save();

                    continue;
                }

                $challenge->attempts++;

                $expectedHash = $this->codeHash($code, (string) $challenge->id);

                if (!hash_equals($challenge->code_hash, $expectedHash)) {
                    if ($challenge->attempts >= self::MAX_CODE_ATTEMPTS) {
                        $challenge->revoked_at = now();
                    }

                    $challenge->save();

                    continue;
                }

                $membership = $employee->memberships()
                    ->where('organization_id', $challenge->organization_id)
                    ->lockForUpdate()
                    ->first();

                if (!$membership || $membership->status === 'blocked') {
                    $challenge->forceFill([
                        'revoked_at' => now(),
                    ])->save();

                    continue;
                }

                $challenge->forceFill([
                    'consumed_at' => now(),
                ])->save();

                $matchedChallenge = $challenge;
                break;
            }

            if (!$matchedChallenge) {
                return false;
            }

            // Телефон — одна общая учётная запись для всех организаций.
            // После её активации все остальные коды становятся недействительными.
            EmployeePhoneChallenge::query()
                ->where('employee_id', $employee->id)
                ->where('id', '!=', $matchedChallenge->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $employee->forceFill([
                'phone_verified_at' => now(),
                'password' => $password,
            ])->save();

            return true;
        });
    }

    private function codeHash(string $code, string $challengeId): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for employee phone verification.');
        }

        return hash_hmac(
            'sha256',
            $challengeId . '|' . $code,
            $key
        );
    }
}
