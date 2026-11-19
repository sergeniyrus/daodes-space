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
            ->where(function ($sectionQuery) use ($settings) {
                if (
                    !$settings ||
                    (!$settings->delivery_enabled && !$settings->pickup_enabled && !$settings->dine_in_enabled)
                ) {
                    $sectionQuery->whereRaw('1 = 0');
                    return;
                }

                // Раздел показывается, только если в нём доступен хотя бы
                // один способ получения, включённый самой организацией.
                if ($settings?->delivery_enabled) {
                    $sectionQuery->where('delivery_enabled', true);
                }

                if ($settings?->pickup_enabled) {
                    $method = $settings?->delivery_enabled ? 'orWhere' : 'where';
                    $sectionQuery->{$method}('pickup_enabled', true);
                }

                if ($settings?->dine_in_enabled) {
                    $sectionQuery->orWhereRaw('1 = 1');
                }
            })
            ->with(['activeItems' => function ($itemQuery) use ($settings) {
                $itemQuery->where(function ($availabilityQuery) use ($settings) {
                    if (
                        !$settings ||
                        (!$settings->delivery_enabled && !$settings->pickup_enabled && !$settings->dine_in_enabled)
                    ) {
                        $availabilityQuery->whereRaw('1 = 0');
                        return;
                    }

                    if ($settings?->delivery_enabled) {
                        $availabilityQuery->where('delivery_enabled', true);
                    }

                    if ($settings?->pickup_enabled) {
                        $method = $settings?->delivery_enabled ? 'orWhere' : 'where';
                        $availabilityQuery->{$method}('pickup_enabled', true);
                    }

                    if ($settings?->dine_in_enabled) {
                        $availabilityQuery->orWhereRaw('1 = 1');
                    }
                });
            }])
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
                ->where(function ($query) use ($settings) {
                    if ($settings?->delivery_enabled) {
                        $query->where('delivery_enabled', true);
                    }
                    if ($settings?->pickup_enabled) {
                        $method = $settings?->delivery_enabled ? 'orWhere' : 'where';
                        $query->{$method}('pickup_enabled', true);
                    }
                    if ($settings?->dine_in_enabled) {
                        $query->orWhereRaw('1 = 1');
                    }
                    if (!$settings || (!$settings->delivery_enabled && !$settings->pickup_enabled && !$settings->dine_in_enabled)) {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->whereHas('section', function ($query) use ($organization, $settings) {
                    $query->where('organization_id', $organization->id)
                        ->where('is_active', true)
                        ->where(function ($availabilityQuery) use ($settings) {
                            if ($settings?->delivery_enabled) {
                                $availabilityQuery->where(function ($methodQuery) {
                                    $methodQuery->where('menu_sections.delivery_enabled', true)
                                        ->where('menu_items.delivery_enabled', true);
                                });
                            }
                            if ($settings?->pickup_enabled) {
                                $method = $settings?->delivery_enabled ? 'orWhere' : 'where';
                                $availabilityQuery->{$method}(function ($methodQuery) {
                                    $methodQuery->where('menu_sections.pickup_enabled', true)
                                        ->where('menu_items.pickup_enabled', true);
                                });
                            }
                            if ($settings?->dine_in_enabled) {
                                $availabilityQuery->orWhereRaw('1 = 1');
                            }
                            if (!$settings || (!$settings->delivery_enabled && !$settings->pickup_enabled && !$settings->dine_in_enabled)) {
                                $availabilityQuery->whereRaw('1 = 0');
                            }
                        });
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
