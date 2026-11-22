<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json([
            'data' => $customer->addresses()
                ->orderByDesc('is_favorite')
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get()
                ->map(fn (CustomerAddress $address) => $this->payload($address))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $validated = $this->validated($request);

        $address = DB::transaction(function () use ($customer, $validated, $request) {
            $first = !$customer->addresses()->exists();
            $isDefault = $first || $request->boolean('is_default');

            if ($isDefault) {
                $customer->addresses()->update(['is_default' => false]);
            }

            return $customer->addresses()->create(array_merge($validated, [
                'is_default' => $isDefault,
                'is_favorite' => $request->boolean('is_favorite'),
            ]));
        });

        return response()->json(['data' => $this->payload($address)], 201);
    }

    public function update(Request $request, CustomerAddress $address): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        abort_unless((int) $address->customer_id === (int) $customer->id, 404);
        $validated = $this->validated($request);

        DB::transaction(function () use ($customer, $address, $validated, $request) {
            if ($request->boolean('is_default')) {
                $customer->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->update(array_merge($validated, [
                'is_default' => $request->boolean('is_default'),
                'is_favorite' => $request->boolean('is_favorite'),
            ]));
        });

        return response()->json(['data' => $this->payload($address->fresh())]);
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        abort_unless((int) $address->customer_id === (int) $customer->id, 404);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $customer->addresses()->latest('updated_at')->first()?->update(['is_default' => true]);
        }

        return response()->json(['deleted' => true, 'address_id' => $address->id]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'address_line' => ['required', 'string', 'max:500'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'entrance' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:30'],
            'apartment' => ['nullable', 'string', 'max:30'],
            'intercom' => ['nullable', 'string', 'max:50'],
            'gate_code' => ['nullable', 'string', 'max:100'],
            'delivery_comment' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
    }

    private function payload(CustomerAddress $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'recipient_phone' => $address->recipient_phone,
            'address_line' => $address->address_line,
            'house_number' => $address->house_number,
            'entrance' => $address->entrance,
            'floor' => $address->floor,
            'apartment' => $address->apartment,
            'intercom' => $address->intercom,
            'gate_code' => $address->gate_code,
            'delivery_comment' => $address->delivery_comment,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'is_default' => $address->is_default,
            'is_favorite' => $address->is_favorite,
        ];
    }
}
