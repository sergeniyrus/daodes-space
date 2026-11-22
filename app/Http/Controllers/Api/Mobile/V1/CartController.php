<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Services\CustomerCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function show(Request $request, CustomerCartService $cartService): JsonResponse
    {
        return response()->json(['data' => $this->cartPayload($request->user(), $cartService)]);
    }

    public function add(Request $request, CustomerCartService $cartService): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'min:1'],
            'item_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $organization = Organization::query()
            ->with('orderSettings')
            ->where('status', 'active')
            ->findOrFail($validated['organization_id']);
        $settings = $organization->orderSettings;

        if (!$settings || !$settings->canAcceptOrders()) {
            throw ValidationException::withMessages([
                'organization_id' => 'Организация временно не принимает онлайн-заказы.',
            ]);
        }

        $hasPaymentAtReceipt = $settings->cash_payment_enabled || $settings->card_payment_enabled;
        $item = MenuItem::query()
            ->whereKey($validated['item_id'])
            ->where('is_active', true)
            ->whereHas('section', function ($query) use ($organization) {
                $query->where('organization_id', $organization->id)->where('is_active', true);
            })
            ->where(function ($query) use ($settings, $hasPaymentAtReceipt) {
                $query->whereRaw('1 = 0');

                if ($settings->hasConfiguredDelivery() && $hasPaymentAtReceipt) {
                    $query->orWhere(fn ($q) => $q->where('delivery_enabled', true)
                        ->whereHas('section', fn ($s) => $s->where('delivery_enabled', true)));
                }

                if ($settings->pickup_enabled && $settings->hasPickupAddress() && $hasPaymentAtReceipt) {
                    $query->orWhere(fn ($q) => $q->where('pickup_enabled', true)
                        ->whereHas('section', fn ($s) => $s->where('pickup_enabled', true)));
                }

                if ($settings->dine_in_enabled) {
                    $query->orWhere(fn ($q) => $q->where('dine_in_enabled', true)
                        ->whereHas('section', fn ($s) => $s->where('dine_in_enabled', true)));
                }
            })
            ->firstOrFail();

        $cart = $cartService->loadForOrganization($customer, (int) $organization->id);
        $quantity = (int) ($cart['items'][$item->id] ?? 0) + (int) ($validated['quantity'] ?? 1);

        if ($quantity > 99) {
            throw ValidationException::withMessages([
                'quantity' => 'Для одной позиции можно заказать не более 99 единиц.',
            ]);
        }

        $cart['items'][$item->id] = $quantity;
        $cartService->save($customer, $cart);

        return response()->json(['data' => $this->cartPayload($customer, $cartService)], 201);
    }

    public function update(Request $request, int $menuItem, CustomerCartService $cartService): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cart = $cartService->loadForOrganization($customer);
        $organizationId = (int) ($cart['organization_id'] ?? 0);

        abort_unless($organizationId > 0 && array_key_exists($menuItem, $cart['items'] ?? []), 404);

        $item = MenuItem::query()
            ->whereKey($menuItem)
            ->where('is_active', true)
            ->whereHas('section', fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->where('is_active', true))
            ->firstOrFail();

        $cart['items'][$item->id] = (int) $validated['quantity'];
        $cartService->save($customer, $cart);

        return response()->json(['data' => $this->cartPayload($customer, $cartService)]);
    }

    public function remove(Request $request, int $menuItem, CustomerCartService $cartService): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $cart = $cartService->loadForOrganization($customer);

        abort_unless(isset($cart['items'][$menuItem]), 404);

        unset($cart['items'][$menuItem]);
        $cartService->save($customer, $cart);

        return response()->json(['data' => $this->cartPayload($customer, $cartService)]);
    }

    public function clear(Request $request, CustomerCartService $cartService): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $cart = $cartService->loadForOrganization($customer);
        $cartService->clearForOrganization($customer, (int) ($cart['organization_id'] ?? 0));

        return response()->json(['data' => $this->cartPayload($customer, $cartService)]);
    }

    private function cartPayload(Customer $customer, CustomerCartService $cartService): array
    {
        $cart = $cartService->loadForOrganization($customer);
        $organizationId = (int) ($cart['organization_id'] ?? 0);
        $quantities = $cart['items'] ?? [];

        if ($organizationId < 1 || $quantities === []) {
            return [
                'organization' => null,
                'items' => [],
                'subtotal' => '0.00',
                'currency' => null,
                'can_order' => false,
            ];
        }

        $organization = Organization::query()
            ->with('orderSettings')
            ->where('status', 'active')
            ->find($organizationId);

        if (!$organization) {
            $cartService->clearForOrganization($customer, $organizationId);

            return [
                'organization' => null,
                'items' => [],
                'subtotal' => '0.00',
                'currency' => null,
                'can_order' => false,
            ];
        }

        $settings = $organization->orderSettings;
        $items = MenuItem::query()
            ->with('section')
            ->whereIn('id', array_map('intval', array_keys($quantities)))
            ->whereHas('section', fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->where('is_active', true))
            ->get()
            ->keyBy('id');

        $subtotalCents = 0;
        $lines = [];

        foreach ($quantities as $itemId => $quantity) {
            $item = $items->get((int) $itemId);
            if (!$item) {
                $lines[] = [
                    'item_id' => (int) $itemId,
                    'name' => 'Недоступный товар',
                    'quantity' => (int) $quantity,
                    'available' => false,
                    'unit_price' => null,
                    'line_total' => null,
                ];
                continue;
            }

            $section = $item->section;
            $hasPaymentAtReceipt = $settings
                && ($settings->cash_payment_enabled || $settings->card_payment_enabled);
            $availability = [
                'delivery' => (bool) (
                    $settings?->hasConfiguredDelivery()
                    && $hasPaymentAtReceipt
                    && $item->delivery_enabled
                    && $section?->delivery_enabled
                ),
                'pickup' => (bool) (
                    $settings?->pickup_enabled
                    && $settings->hasPickupAddress()
                    && $hasPaymentAtReceipt
                    && $item->pickup_enabled
                    && $section?->pickup_enabled
                ),
                'dine_in' => (bool) (
                    $settings?->dine_in_enabled
                    && $item->dine_in_enabled
                    && $section?->dine_in_enabled
                ),
            ];
            $available = $item->is_active && in_array(true, $availability, true);
            $lineCents = $this->toCents($item->price) * (int) $quantity;
            if ($available) {
                $subtotalCents += $lineCents;
            }

            $lines[] = [
                'item_id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'weight' => $item->weight,
                'unit' => $item->unit,
                'image_url' => $item->image_url,
                'quantity' => (int) $quantity,
                'unit_price' => (string) $item->price,
                'line_total' => $available ? $this->fromCents($lineCents) : null,
                'availability' => $availability,
                'available' => $available,
            ];
        }

        return [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'items' => $lines,
            'subtotal' => $this->fromCents($subtotalCents),
            'currency' => $settings?->currency,
            'can_order' => (bool) ($settings?->canAcceptOrders() && collect($lines)->every(fn ($line) => $line['available'])),
        ];
    }

    private function toCents(string|int|float|null $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) ($amount ?? '0'), 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
