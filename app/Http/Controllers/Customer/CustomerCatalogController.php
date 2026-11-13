<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
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

        if ((int) ($cart['organization_id'] ?? 0) === (int) $organization->id) {
            $cartCount = array_sum($cart['items'] ?? []);
        }

        return view('customer.catalog.index', [
            'organization' => $organization,
            'settings' => $settings,
            'sections' => $sections,
            'canOrder' => $settings?->canAcceptOrders() ?? false,
            'cartCount' => $cartCount,
        ]);
    }
}
