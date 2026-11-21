<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\EmployeePhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use InvalidArgumentException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $phone = EmployeePhone::normalize($validated['phone']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['phone' => [$exception->getMessage()]],
            ], 422);
        }

        $throttleKey = 'mobile-customer-login:' . hash('sha256', $phone . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return response()->json([
                'message' => 'Слишком много попыток входа. Повторите позже.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        $customer = Customer::query()->where('phone', $phone)->first();

        if (!$customer || !Hash::check($validated['password'], $customer->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return response()->json([
                'message' => 'Неверный номер телефона или пароль.',
            ], 422);
        }

        if (!$customer->phone_verified_at) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return response()->json([
                'message' => 'Сначала подтвердите номер телефона через SMS и установите пароль.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $token = $customer->createToken(
            $validated['device_name'] ?? 'DAODES Mobile',
            ['mobile'],
            now()->addDays(30)
        );

        return response()->json([
            'message' => 'Авторизация выполнена.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'customer' => $this->customerPayload($customer),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'customer' => $this->customerPayload($this->authenticatedCustomer($request)),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authenticatedCustomer($request)->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Сеанс мобильного приложения завершён.',
        ]);
    }

    private function authenticatedCustomer(Request $request): Customer
    {
        $customer = $request->user();

        abort_unless($customer instanceof Customer, 401);

        $accessToken = $customer->currentAccessToken();

        abort_unless(
            $accessToken instanceof PersonalAccessToken && $accessToken->can('mobile'),
            401
        );

        return $customer;
    }

    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'phone_verified_at' => $customer->phone_verified_at?->toIso8601String(),
        ];
    }
}
