<?php

namespace App\Services\Iiko;

use App\Exceptions\IikoApiException;
use App\Models\IikoConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class IikoCloudClient
{
    public function __construct(
        private readonly IikoAuthService $authService
    ) {
    }

    /**
     * Send an authenticated request to the Russian iikoCloud API.
     * API paths must include their documented /api/... prefix.
     */
    public function request(
        IikoConnection $connection,
        string $method,
        string $path,
        array $payload = []
    ): array {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');

        $response = $this->pendingRequest($connection)
            ->send($method, $path, ['json' => $payload]);

        if ($response->status() === 401) {
            $this->authService->forgetToken($connection);

            $response = $this->pendingRequest($connection)
                ->send($method, $path, ['json' => $payload]);
        }

        return $this->decodeResponse($response);
    }

    private function pendingRequest(IikoConnection $connection): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('iiko.base_url'), '/'))
            ->withToken($this->authService->token($connection))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('iiko.timeout', 20))
            ->connectTimeout((int) config('iiko.connect_timeout', 5));
    }

    private function decodeResponse(Response $response): array
    {
        $payload = $response->json();

        if (!$response->successful()) {
            throw new IikoApiException(
                'Ошибка запроса к iikoCloud.',
                $response->status(),
                is_array($payload) ? $payload : $response->body()
            );
        }

        if ($payload === null) {
            return [];
        }

        if (!is_array($payload)) {
            throw new IikoApiException(
                'iikoCloud вернул ответ в неожиданном формате.',
                $response->status(),
                $response->body()
            );
        }

        return $payload;
    }
}
