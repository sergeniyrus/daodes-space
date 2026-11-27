<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        $organizations = Organization::query()
            ->where('status', 'active')
            ->whereHas('menuSections', function ($query) {
                $query->where('is_active', true)
                    ->whereHas('activeItems');
            })
            ->with('orderSettings')
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization): array {
                $settings = $organization->orderSettings;

                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'can_order' => $settings?->canAcceptOrders() ?? false,
                    'fulfillments' => $this->availableFulfillments($settings),
                ];
            })
            ->values();

        return response()->json([
            'data' => $organizations,
        ]);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;
        $availableFulfillments = $this->availableFulfillments($settings);

        $defaultFulfillment = $availableFulfillments['pickup']
            ? 'pickup'
            : ($availableFulfillments['dine_in']
                ? 'dine_in'
                : ($availableFulfillments['delivery'] ? 'delivery' : null));

        $requestedFulfillment = $request->query('fulfillment');
        $selectedFulfillment = is_string($requestedFulfillment)
            && ($availableFulfillments[$requestedFulfillment] ?? false)
                ? $requestedFulfillment
                : $defaultFulfillment;

        $sections = $organization->menuSections()
            ->where('is_active', true)
            ->when($selectedFulfillment === 'delivery', fn ($query) => $query->where('delivery_enabled', true))
            ->when($selectedFulfillment === 'pickup', fn ($query) => $query->where('pickup_enabled', true))
            ->when($selectedFulfillment === 'dine_in', fn ($query) => $query->where('dine_in_enabled', true))
            ->when(!$selectedFulfillment, fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['activeItems' => function ($query) use ($selectedFulfillment) {
                $query
                    ->when($selectedFulfillment === 'delivery', fn ($items) => $items->where('delivery_enabled', true))
                    ->when($selectedFulfillment === 'pickup', fn ($items) => $items->where('pickup_enabled', true))
                    ->when($selectedFulfillment === 'dine_in', fn ($items) => $items->where('dine_in_enabled', true))
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($section) => $section->activeItems->isNotEmpty())
            ->values()
            ->map(fn ($section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'sort_order' => $section->sort_order,
                'items' => $section->activeItems->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'weight' => $item->weight,
                    'unit' => $item->unit,
                    'price' => $item->price,
                    'image_url' => $item->image_url,
                ])->values(),
            ]);

        return response()->json([
            'data' => [
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ],
                'can_order' => $settings?->canAcceptOrders() ?? false,
                'currency' => $settings?->currency,
                'selected_fulfillment' => $selectedFulfillment,
                'fulfillments' => $availableFulfillments,
                'sections' => $sections,
            ],
        ]);
    }

    private function availableFulfillments($settings): array
    {
        $hasPaymentAtReceipt = $settings
            && ($settings->cash_payment_enabled || $settings->card_payment_enabled);

        return [
            'delivery' => (bool) (
                $settings?->delivery_enabled
                && $settings->hasConfiguredDelivery()
                && $hasPaymentAtReceipt
            ),
            'pickup' => (bool) (
                $settings?->pickup_enabled
                && $settings->hasPickupAddress()
                && $hasPaymentAtReceipt
            ),
            'dine_in' => (bool) $settings?->dine_in_enabled,
        ];
    }
}
