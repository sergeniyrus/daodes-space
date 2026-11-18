<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerCatalogController extends Controller
{
    /**
     * Публичный список активных организаций с онлайн-каталогом.
     */
    public function organizations(): View
    {
        $organizations = Organization::query()
            ->where('status', 'active')
            ->whereHas('menuSections', function ($query) {
                $query->where('is_active', true);
            })
            ->with('orderSettings')
            ->orderBy('name')
            ->get();

        return view('customer.organizations.index', [
            'organizations' => $organizations,
        ]);
    }

    public function index(Organization $organization): View
    {
        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;

        $sections = $organization->menuSections()
            ->where('is_active', true)
            ->with(['activeItems'])
            ->orderBy('sort_order')
            ->get();

        $cart = session('customer_order_cart', []);
        $cartCount = 0;
        $cartSubtotalCents = 0;

        if (
            (int) ($cart['organization_id'] ?? 0) === (int) $organization->id
            && is_array($cart['items'] ?? null)
            && $cart['items'] !== []
        ) {
            $quantities = $cart['items'];
            $cartCount = array_sum(array_map('intval', $quantities));

            $cartItems = MenuItem::query()
                ->whereIn('id', array_map('intval', array_keys($quantities)))
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->where('delivery_enabled', true)
                        ->orWhere('pickup_enabled', true);
                })
                ->whereHas('section', function ($query) use ($organization) {
                    $query->where('organization_id', $organization->id)
                        ->where('is_active', true);
                })
                ->get()
                ->keyBy('id');

            foreach ($quantities as $itemId => $quantity) {
                $item = $cartItems->get((int) $itemId);
                $quantity = (int) $quantity;

                if ($item && $quantity > 0 && $quantity <= 99) {
                    $cartSubtotalCents += (int) round((float) $item->price * 100) * $quantity;
                }
            }
        }

        $customer = Auth::guard('customer')->user();
        $ordersQuery = $customer
            ? $customer->orders()->where('organization_id', $organization->id)
            : null;

        $ordersCount = $ordersQuery ? (clone $ordersQuery)->count() : 0;
        $ordersInProgressCount = $ordersQuery
            ? (clone $ordersQuery)->whereNotIn('status', [
                Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ])->count()
            : 0;
        $ordersHistoryCount = $ordersCount - $ordersInProgressCount;

        return view('customer.catalog.index', [
            'organization' => $organization,
            'settings' => $settings,
            'sections' => $sections,
            'canOrder' => $settings?->canAcceptOrders() ?? false,
            'cartCount' => $cartCount,
            'cartSubtotal' => $cartSubtotalCents / 100,
            'ordersCount' => $ordersCount,
            'ordersHistoryCount' => $ordersHistoryCount,
            'ordersInProgressCount' => $ordersInProgressCount,
            'currencySymbol' => $settings?->currency === 'RUB' ? '₽' : ($settings?->currency ?? ''),
        ]);
    }
}
