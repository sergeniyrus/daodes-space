<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Services\FirebaseMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function store(Request $request, FirebaseMessagingService $messaging): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        abort_unless($customer->phone_verified_at, 403);
        abort_unless($messaging->isConfigured(), 503, 'Push notifications are not configured.');

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $device = CustomerDevice::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $validated['token'])],
            [
                'customer_id' => $customer->id,
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'push_token' => $validated['token'],
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['subscribed' => true, 'device_id' => $device->id], 201)
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        CustomerDevice::query()
            ->where('customer_id', $customer->id)
            ->where('token_hash', hash('sha256', $validated['token']))
            ->update(['is_active' => false]);

        return response()->json(['subscribed' => false])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
