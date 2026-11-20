<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
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

    public function index(Request $request, Organization $organization): View
    {
        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;
        $hasPaymentAtReceipt = $settings
            && ($settings->cash_payment_enabled || $settings->card_payment_enabled);

        $availableFulfillments = [
            'delivery' => (bool) (
                $settings?->delivery_enabled
                && $settings->hasConfiguredDelivery()
                && $hasPaymentAtReceipt
            ),
            'pickup' => (bool) ($settings?->pickup_enabled && $hasPaymentAtReceipt),
            'dine_in' => (bool) $settings?->dine_in_enabled,
        ];

        $defaultFulfillment = $availableFulfillments['pickup']
            ? 'pickup'
            : ($availableFulfillments['dine_in']
                ? 'dine_in'
                : ($availableFulfillments['delivery'] ? 'delivery' : null));

        $sessionKey = 'customer_order_fulfillment_' . $organization->id;
        $requestedFulfillment = $request->query('fulfillment');
        $selectedFulfillment = is_string($requestedFulfillment)
            && ($availableFulfillments[$requestedFulfillment] ?? false)
                ? $requestedFulfillment
                : session($sessionKey, $defaultFulfillment);

        if (!($availableFulfillments[$selectedFulfillment] ?? false)) {
            $selectedFulfillment = $defaultFulfillment;
        }

        if ($selectedFulfillment) {
            session([$sessionKey => $selectedFulfillment]);
        }

        $publicRating = OrderReview::query()->where('organization_id', $organization->id)
            ->where('is_public', true)->whereNotNull('published_at')->whereNotNull('rating')
            ->selectRaw('COUNT(DISTINCT order_id) as total, AVG(rating) as average')->first();

        $sectionsQuery = $organization->menuSections()
            ->where('is_active', true);

        if ($selectedFulfillment === 'delivery') {
            $sectionsQuery->where('delivery_enabled', true);
        } elseif ($selectedFulfillment === 'pickup') {
            $sectionsQuery->where('pickup_enabled', true);
        } elseif ($selectedFulfillment === 'dine_in') {
            $sectionsQuery->where('dine_in_enabled', true);
        }

        $sections = $sectionsQuery
            ->with(['activeItems' => function ($itemQuery) use ($selectedFulfillment) {
                if ($selectedFulfillment === 'delivery') {
                    $itemQuery->where('delivery_enabled', true);
                } elseif ($selectedFulfillment === 'pickup') {
                    $itemQuery->where('pickup_enabled', true);
                } elseif ($selectedFulfillment === 'dine_in') {
                    $itemQuery->where('dine_in_enabled', true);
                }
            }])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($section) => $section->activeItems->isNotEmpty())
            ->values();

        $cart = session('customer_order_cart', []);
        $cartCount = 0;
        $cartSubtotalCents = 0;
        $cartUnavailableCount = 0;

        if (
            (int) ($cart['organization_id'] ?? 0) === (int) $organization->id
            && is_array($cart['items'] ?? null)
            && $cart['items'] !== []
        ) {
            $quantities = $cart['items'];
            $cartCount = array_sum(array_map('intval', $quantities));

            $cartItemsQuery = MenuItem::query()
                ->whereIn('id', array_map('intval', array_keys($quantities)))
                ->where('is_active', true);

            if ($selectedFulfillment === 'delivery') {
                $cartItemsQuery->where('delivery_enabled', true);
            } elseif ($selectedFulfillment === 'pickup') {
                $cartItemsQuery->where('pickup_enabled', true);
            } elseif ($selectedFulfillment === 'dine_in') {
                $cartItemsQuery->where('dine_in_enabled', true);
            } elseif (!$selectedFulfillment) {
                $cartItemsQuery->whereRaw('1 = 0');
            }

            $cartItems = $cartItemsQuery
                ->whereHas('section', function ($query) use ($organization, $selectedFulfillment) {
                    $query->where('organization_id', $organization->id)
                        ->where('is_active', true);

                    if ($selectedFulfillment === 'delivery') {
                        $query->where('delivery_enabled', true);
                    } elseif ($selectedFulfillment === 'pickup') {
                        $query->where('pickup_enabled', true);
                    } elseif ($selectedFulfillment === 'dine_in') {
                        $query->where('dine_in_enabled', true);
                    }
                })
                ->get()
                ->keyBy('id');

            $cartUnavailableCount = count($quantities) - $cartItems->count();

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
            'publicRating' => $publicRating,
            'settings' => $settings,
            'sections' => $sections,
            'canOrder' => $settings?->canAcceptOrders() ?? false,
            'selectedFulfillment' => $selectedFulfillment,
            'availableFulfillments' => $availableFulfillments,
            'cartCount' => $cartCount,
            'cartSubtotal' => $cartSubtotalCents / 100,
            'cartUnavailableCount' => $cartUnavailableCount,
            'ordersCount' => $ordersCount,
            'ordersHistoryCount' => $ordersHistoryCount,
            'ordersInProgressCount' => $ordersInProgressCount,
            'currencySymbol' => $settings?->currency === 'RUB' ? '₽' : ($settings?->currency ?? ''),
        ]);
    }

}
