<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Models\CustomerDevice;
use App\Services\FirebaseMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class CustomerPushController extends Controller
{
    public function store(Request $request, FirebaseMessagingService $messaging): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer && $customer->phone_verified_at, 403);
        abort_unless($messaging->isConfigured(), 503, 'Push notifications are not configured.');
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', 'in:web,android,ios'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);
        if ($validated['platform'] === 'web' && !$this->webPushIsConfigured()) {
            abort(503, 'Web Push is not configured.');
        }
        $token = $validated['token'];
        $device = CustomerDevice::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $token)],
            [
                'customer_id' => $customer->id,
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'push_token' => $token,
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );
        return response()->json(['subscribed' => true, 'device_id' => $device->id], 201)
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
    public function destroy(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless($customer, 401);
        $validated = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        CustomerDevice::query()
            ->where('customer_id', $customer->id)
            ->where('token_hash', hash('sha256', $validated['token']))
            ->update(['is_active' => false]);
        return response()->json(['subscribed' => false])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
    public function serviceWorker(): \Illuminate\Http\Response
    {
        abort_unless($this->webPushIsConfigured(), 503);
        $configJson = json_encode($this->webFirebaseConfig(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $script = <<<JS
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');
firebase.initializeApp({$configJson});
firebase.messaging();
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : '/customer/orders';
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
        for (const client of clients) {
            if ('focus' in client) {
                client.navigate(targetUrl);
                return client.focus();
            }
        }
        return self.clients.openWindow(targetUrl);
    }));
});
JS;
        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
    private function webPushIsConfigured(): bool
    {
        $web = config('services.firebase.web', []);
        return filled($web['api_key'] ?? null)
            && filled($web['auth_domain'] ?? null)
            && filled($web['project_id'] ?? null)
            && filled($web['messaging_sender_id'] ?? null)
            && filled($web['app_id'] ?? null)
            && filled($web['vapid_key'] ?? null);
    }
    private function webFirebaseConfig(): array
    {
        $web = config('services.firebase.web', []);
        return [
            'apiKey' => $web['api_key'],
            'authDomain' => $web['auth_domain'],
            'projectId' => $web['project_id'],
            'storageBucket' => $web['storage_bucket'] ?? '',
            'messagingSenderId' => $web['messaging_sender_id'],
            'appId' => $web['app_id'],
        ];
    }
}
