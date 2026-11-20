<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Services\CustomerCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCartController extends Controller
{
    private const SESSION_KEY = 'customer_order_cart';

    public function index(CustomerCartService $cartService): View
    {
        /** @var Customer|null $customer */
        $customer = auth('customer')->user();

        $sessionCart = session(self::SESSION_KEY, []);
        $cart = $customer
            ? $cartService->loadForOrganization(
                $customer,
                (int) ($sessionCart['organization_id'] ?? 0) ?: null
            )
            : $sessionCart;

        $organizationId = (int) ($cart['organization_id'] ?? 0);
        $quantities = $cart['items'] ?? [];

        if ($organizationId < 1 || !is_array($quantities) || $quantities === []) {
            return view('customer.cart.index', [
                'organization' => null,
                'lines' => collect(),
                'subtotal' => 0,
                'canOrder' => false,
            ]);
        }

        $organization = Organization::query()
            ->with('orderSettings')
            ->find($organizationId);

        if (!$organization || !$organization->isActive()) {
            $cartService->clearForOrganization($customer, $organizationId);

            return view('customer.cart.index', [
                'organization' => null,
                'lines' => collect(),
                'subtotal' => 0,
                'canOrder' => false,
            ]);
        }

        $items = MenuItem::query()
            ->with('section')
            ->whereIn('id', array_map('intval', array_keys($quantities)))
            ->whereHas('section', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId)
                    ->where('is_active', true);
            })
            ->get()
            ->keyBy('id');

        $lines = collect($quantities)->map(function ($quantity, $itemId) use ($items, $organization) {
            $item = $items->get((int) $itemId);
            $section = $item?->section;
            $settings = $organization->orderSettings;
            $hasPaymentAtReceipt = $settings
                && ($settings->cash_payment_enabled || $settings->card_payment_enabled);

            $deliveryAvailable = $item !== null && $item->is_active && $section !== null
                && $settings?->hasConfiguredDelivery() && $hasPaymentAtReceipt
                && $item->delivery_enabled && $section->delivery_enabled;
            $pickupAvailable = $item !== null && $item->is_active && $section !== null
                && $settings?->pickup_enabled && $hasPaymentAtReceipt
                && $item->pickup_enabled && $section->pickup_enabled;
            $dineInAvailable = $item !== null && $item->is_active && $section !== null
                && (bool) ($settings?->dine_in_enabled)
                && $item->dine_in_enabled
                && $section->dine_in_enabled;
            $available = $deliveryAvailable || $pickupAvailable || $dineInAvailable;

            return [
                'item_id' => (int) $itemId,
                'item' => $item,
                'quantity' => (int) $quantity,
                'delivery_available' => (bool) $deliveryAvailable,
                'pickup_available' => (bool) $pickupAvailable,
                'dine_in_available' => $dineInAvailable,
                'available' => $available,
                'line_total' => $available ? (float) $item->price * (int) $quantity : 0,
            ];
        })->values();

        $subtotal = $lines->sum('line_total');

        return view('customer.cart.index', [
            'organization' => $organization,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'canOrder' => $organization->orderSettings?->canAcceptOrders() ?? false,
        ]);
    }

    public function add(
        Request $request,
        Organization $organization,
        CustomerCartService $cartService
    ): RedirectResponse {
        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;

        if (!$settings || !$settings->canAcceptOrders()) {
            return redirect()
                ->route('customer.catalog', $organization)
                ->with('error', 'Организация пока не принимает онлайн-заказы.');
        }

        $hasPaymentAtReceipt = $settings->cash_payment_enabled || $settings->card_payment_enabled;
        $canAddDelivery = $settings->hasConfiguredDelivery() && $hasPaymentAtReceipt;
        $canAddPickup = $settings->pickup_enabled && $hasPaymentAtReceipt;
        $canAddDineIn = $settings->dine_in_enabled;

        $validated = $request->validate([
            'item_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $item = MenuItem::query()
            ->whereKey($validated['item_id'])
            ->where('is_active', true)
            ->whereHas('section', function ($query) use ($organization) {
                $query->where('organization_id', $organization->id)
                    ->where('is_active', true);
            })
            ->where(function ($availabilityQuery) use ($canAddDelivery, $canAddPickup, $canAddDineIn) {
                $availabilityQuery->whereRaw('1 = 0');

                if ($canAddDelivery) {
                    $availabilityQuery->orWhere(function ($methodQuery) {
                        $methodQuery->where('delivery_enabled', true)
                            ->whereHas('section', fn ($sectionQuery) => $sectionQuery->where('delivery_enabled', true));
                    });
                }

                if ($canAddPickup) {
                    $availabilityQuery->orWhere(function ($methodQuery) {
                        $methodQuery->where('pickup_enabled', true)
                            ->whereHas('section', fn ($sectionQuery) => $sectionQuery->where('pickup_enabled', true));
                    });
                }

                if ($canAddDineIn) {
                    $availabilityQuery->orWhere(function ($methodQuery) {
                        $methodQuery->where('dine_in_enabled', true)
                            ->whereHas('section', fn ($sectionQuery) => $sectionQuery->where('dine_in_enabled', true));
                    });
                }
            })
            ->firstOrFail();

        /** @var Customer|null $customer */
        $customer = auth('customer')->user();

        $cart = $customer
            ? $cartService->loadForOrganization($customer, (int) $organization->id)
            : session(self::SESSION_KEY, [
                'organization_id' => $organization->id,
                'items' => [],
            ]);

        if (
            !empty($cart['items'])
            && (int) ($cart['organization_id'] ?? 0) !== (int) $organization->id
        ) {
            return redirect()
                ->route('customer.catalog', $organization)
                ->with('error', 'В корзине уже есть товары другой организации. Сначала очистите текущую корзину.');
        }

        if ((int) ($cart['organization_id'] ?? 0) !== (int) $organization->id) {
            $cart = [
                'organization_id' => $organization->id,
                'items' => [],
            ];
        }

        $currentQuantity = (int) ($cart['items'][$item->id] ?? 0);
        $newQuantity = $currentQuantity + (int) ($validated['quantity'] ?? 1);

        if ($newQuantity > 99) {
            return redirect()
                ->route('customer.catalog', $organization)
                ->with('error', 'Для одной позиции можно заказать не более 99 единиц.');
        }

        $cart['items'][$item->id] = $newQuantity;
        $cartService->save($customer, $cart);
        session()->forget('customer_order_checkout_snapshots');
        session()->forget('customer_order_checkout_snapshot');

        $catalogUrl = route('customer.catalog', $organization);
        $sectionId = (int) ($item->section?->getKey() ?? 0);

        if ($sectionId > 0) {
            $catalogUrl .= '?section=' . $sectionId;
        }

        return redirect()
            ->to($catalogUrl . '#menu-item-' . $item->id)
            ->with('success', 'Товар добавлен в корзину.');
    }

    public function update(
        Request $request,
        MenuItem $menuItem,
        CustomerCartService $cartService
    ): RedirectResponse {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        /** @var Customer|null $customer */
        $customer = auth('customer')->user();
        $sessionCart = session(self::SESSION_KEY, []);
        $cart = $customer
            ? $cartService->loadForOrganization(
                $customer,
                (int) ($sessionCart['organization_id'] ?? 0) ?: null
            )
            : $sessionCart;

        $organizationId = (int) ($cart['organization_id'] ?? 0);

        $organization = $organizationId > 0
            ? Organization::query()->with('orderSettings')->find($organizationId)
            : null;

        $section = $menuItem->section()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->first();

        $settings = $organization?->orderSettings;

        $available = $organizationId > 0
            && isset($cart['items'][$menuItem->id])
            && $menuItem->is_active
            && $section !== null
            && (
                ($settings?->delivery_enabled && $menuItem->delivery_enabled && $section->delivery_enabled)
                || ($settings?->pickup_enabled && $menuItem->pickup_enabled && $section->pickup_enabled)
                || $settings?->dine_in_enabled
            );

        abort_unless($available, 404);

        $cart['items'][$menuItem->id] = (int) $validated['quantity'];
        $cartService->save($customer, $cart);
        session()->forget('customer_order_checkout_snapshots');
        session()->forget('customer_order_checkout_snapshot');

        return redirect()->to(route('customer.cart') . '#cart-item-' . $menuItem->id);
    }

    public function remove(
        CustomerCartService $cartService,
        int $menuItemId
    ): RedirectResponse {
        /** @var Customer|null $customer */
        $customer = auth('customer')->user();
        $sessionCart = session(self::SESSION_KEY, []);
        $cart = $customer
            ? $cartService->loadForOrganization(
                $customer,
                (int) ($sessionCart['organization_id'] ?? 0) ?: null
            )
            : $sessionCart;

        abort_unless(isset($cart['items'][$menuItemId]), 404);

        unset($cart['items'][$menuItemId]);
        $cartService->save($customer, $cart);

        session()->forget('customer_order_checkout_snapshots');
        session()->forget('customer_order_checkout_snapshot');

        return redirect()->route('customer.cart');
    }

    public function clear(CustomerCartService $cartService): RedirectResponse
    {
        /** @var Customer|null $customer */
        $customer = auth('customer')->user();
        $cart = session(self::SESSION_KEY, []);

        if ($customer) {
            $cart = $cartService->loadForOrganization(
                $customer,
                (int) ($cart['organization_id'] ?? 0) ?: null
            );
        }

        $cartService->clearForOrganization(
            $customer,
            (int) ($cart['organization_id'] ?? 0)
        );

        session()->forget('customer_order_checkout_snapshots');
        session()->forget('customer_order_checkout_snapshot');

        return redirect()
            ->route('customer.cart')
            ->with('success', 'Корзина очищена.');
    }
}
