<?php

namespace App\Services\Iiko;

use App\Exceptions\IikoApiException;
use App\Models\IikoConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IikoAuthService
{
    public function token(IikoConnection $connection): string
    {
        $cacheKey = $connection->tokenCacheKey();
        $minutes = max(1, (int) config('iiko.token_cache_minutes', 55));

        return Cache::remember($cacheKey, now()->addMinutes($minutes), function () use ($connection) {
            $appId = (string) config('iiko.app_id');
            $clientSecret = (string) config('iiko.client_secret');
            $apiKey = (string) $connection->api_key;

            if ($appId === '' || $clientSecret === '' || $apiKey === '') {
                throw new RuntimeException('Не настроены учётные данные iikoCloud.');
            }

            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('iiko.timeout', 20))
                ->connectTimeout((int) config('iiko.connect_timeout', 5))
                ->post(rtrim((string) config('iiko.base_url'), '/') . '/api/v2/access_token', [
                    'appId' => $appId,
                    'clientSecret' => $clientSecret,
                    'apiKey' => $apiKey,
                ]);

            $payload = $response->json();
            $token = is_array($payload) ? ($payload['token'] ?? null) : null;

            if (!$response->successful() || !is_string($token) || $token === '') {
                throw new IikoApiException(
                    'Не удалось получить токен iikoCloud.',
                    $response->status(),
                    is_array($payload) ? $payload : $response->body()
                );
            }

            return $token;
        });
    }

    public function forgetToken(IikoConnection $connection): void
    {
        Cache::forget($connection->tokenCacheKey());
    }
}
