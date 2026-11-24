<?php

namespace App\Http\Controllers;

use App\Models\UserPushDevice;
use App\Services\FirebaseMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatCallPushDeviceController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $enabled = UserPushDevice::query()
            ->where('user_id', Auth::id())
            ->where('token_hash', hash('sha256', $validated['token']))
            ->where('is_active', true)
            ->exists();

        return response()->json(['enabled' => $enabled])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(Request $request, FirebaseMessagingService $messaging): JsonResponse
    {
        abort_unless($messaging->isConfigured(), 503, 'Push notifications are not configured.');

        $web = config('services.firebase.web', []);
        abort_unless(
            filled($web['api_key'] ?? null)
                && filled($web['project_id'] ?? null)
                && filled($web['messaging_sender_id'] ?? null)
                && filled($web['app_id'] ?? null)
                && filled($web['vapid_key'] ?? null),
            503,
            'Web Push is not configured.'
        );

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $token = $validated['token'];
        $device = UserPushDevice::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $token)],
            [
                'user_id' => Auth::id(),
                'push_token' => $token,
                'platform' => 'web',
                'device_name' => $validated['device_name'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'enabled' => true,
            'device_id' => $device->id,
        ], 201)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        UserPushDevice::query()
            ->where('user_id', Auth::id())
            ->where('token_hash', hash('sha256', $validated['token']))
            ->update(['is_active' => false]);

        // Не удаляем FCM token из браузера: он может использоваться
        // отдельной подпиской клиента на уведомления о заказах.
        return response()->json(['enabled' => false])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
