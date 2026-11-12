<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCartController extends Controller
{
    private const SESSION_KEY = 'customer_order_cart';

    public function index(): View
    {
        $cart = session(self::SESSION_KEY, []);
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
            session()->forget(self::SESSION_KEY);

            return view('customer.cart.index', [
                'organization' => null,
                'lines' => collect(),
                'subtotal' => 0,
                'canOrder' => false,
            ]);
        }

        $items = MenuItem::query()
            ->whereIn('id', array_map('intval', array_keys($quantities)))
            ->whereHas('section', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId)
                    ->where('is_active', true);
            })
            ->get()
            ->keyBy('id');

        $lines = collect($quantities)->map(function ($quantity, $itemId) use ($items) {
            $item = $items->get((int) $itemId);

            return [
                'item_id' => (int) $itemId,
                'item' => $item,
                'quantity' => (int) $quantity,
                'available' => $item !== null && $item->is_active,
                'line_total' => $item && $item->is_active
                    ? (float) $item->price * (int) $quantity
                    : 0,
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

    public function add(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;

        if (!$settings || !$settings->canAcceptOrders()) {
            return redirect()
                ->route('customer.catalog', $organization)
                ->with('error', 'Организация пока не принимает онлайн-заказы.');
        }

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
            ->firstOrFail();

        $cart = session(self::SESSION_KEY, [
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
        session()->put(self::SESSION_KEY, $cart);
        session()->forget('customer_order_checkout_snapshot');

        return redirect()
            ->route('customer.catalog', $organization)
            ->with('success', 'Товар добавлен в корзину.');
    }

    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cart = session(self::SESSION_KEY, []);
        $organizationId = (int) ($cart['organization_id'] ?? 0);

        abort_unless(
            $organizationId > 0
            && isset($cart['items'][$menuItem->id])
            && $menuItem->is_active
            && $menuItem->section()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->exists(),
            404
        );

        $cart['items'][$menuItem->id] = (int) $validated['quantity'];
        session()->put(self::SESSION_KEY, $cart);
        session()->forget('customer_order_checkout_snapshot');

        return redirect()->route('customer.cart');
    }

    public function remove(int $menuItemId): RedirectResponse
    {
        $cart = session(self::SESSION_KEY, []);

        abort_unless(
            isset($cart['items'][$menuItemId]),
            404
        );

        unset($cart['items'][$menuItemId]);

        if (empty($cart['items'])) {
            session()->forget(self::SESSION_KEY);
        } else {
            session()->put(self::SESSION_KEY, $cart);
        }

        session()->forget('customer_order_checkout_snapshot');

        return redirect()->route('customer.cart');
    }

    public function clear(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);
        session()->forget('customer_order_checkout_snapshot');

        return redirect()
            ->route('customer.cart')
            ->with('success', 'Корзина очищена.');
    }
}
