<?php
namespace App\Services;
use App\Exceptions\FirebaseMessagingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
class FirebaseMessagingService
{
    public function isConfigured(): bool
    {
        return filled(config('services.firebase.project_id'))
            && filled(config('services.firebase.client_email'))
            && filled(config('services.firebase.private_key'));
    }
    public function send(string $deviceToken, string $title, string $body, array $data = []): void
    {
        if (!$this->isConfigured()) {
            throw new FirebaseMessagingException('Firebase Cloud Messaging is not configured.');
        }
        $url = $data['url'] ?? url('/customer/orders');
        $stringData = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $stringData[(string) $key] = (string) $value;
            }
        }
        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $stringData,
                'webpush' => [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => url('/pwa-icon.svg'),
                        'data' => ['url' => $url],
                    ],
                    'fcm_options' => ['link' => $url],
                ],
                'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
            ],
        ];
        $response = Http::acceptJson()
            ->withToken($this->accessToken())
            ->timeout(20)
            ->post(
                'https://fcm.googleapis.com/v1/projects/' . rawurlencode(config('services.firebase.project_id')) . '/messages:send',
                $payload
            );
        if ($response->successful()) {
            return;
        }
        $details = $response->json('error.details', []);
        $unregistered = $response->status() === 404;
        foreach (is_array($details) ? $details : [] as $detail) {
            if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                $unregistered = true;
                break;
            }
        }
        throw new FirebaseMessagingException(
            'Firebase Cloud Messaging returned HTTP ' . $response->status() . '.',
            $unregistered
        );
    }
    private function accessToken(): string
    {
        $email = (string) config('services.firebase.client_email');
        return Cache::remember('firebase_fcm_access_token_' . hash('sha256', $email), now()->addMinutes(50), function (): string {
            $response = Http::asForm()->acceptJson()->timeout(15)->post(
                config('services.firebase.token_uri', 'https://oauth2.googleapis.com/token'),
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->createSignedAssertion(),
                ]
            );
            if (!$response->successful() || !filled($response->json('access_token'))) {
                throw new FirebaseMessagingException('Unable to obtain a Firebase service-account access token.');
            }
            return (string) $response->json('access_token');
        });
    }
    private function createSignedAssertion(): string
    {
        $now = now()->timestamp;
        $keyValue = str_replace('\\n', "\n", (string) config('services.firebase.private_key'));
        $privateKey = openssl_pkey_get_private($keyValue);
        if ($privateKey === false) {
            throw new FirebaseMessagingException('Firebase service-account private key is invalid.');
        }
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => config('services.firebase.client_email'),
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => config('services.firebase.token_uri', 'https://oauth2.googleapis.com/token'),
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header . '.' . $claims;
        $signature = '';
        if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new FirebaseMessagingException('Unable to sign the Firebase service-account assertion.');
        }
        return $unsigned . '.' . $this->base64UrlEncode($signature);
    }
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
