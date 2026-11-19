<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $customer = Auth::guard('customer')->user();

        $organization = null;
        if ($request->filled('organization')) {
            $organization = Organization::query()
                ->where('status', 'active')
                ->find((int) $request->query('organization'));
        }

        if (!$organization) {
            $organization = $customer->orders()
                ->with('organization.orderSettings')
                ->latest()
                ->first()
                ?->organization;
        }

        $customerWorkspace = null;
        if ($organization) {
            $sections = $organization->menuSections()
                ->where('is_active', true)
                ->with('activeItems')
                ->orderBy('sort_order')
                ->get();

            $customerWorkspace = [
                'organization' => $organization,
                'settings' => $organization->orderSettings,
                'sections' => $sections,
                'canOrder' => false,
                'cartCount' => 0,
                'cartSubtotal' => 0,
                'ordersHistoryCount' => $customer->orders()->count(),
                'ordersInProgressCount' => 0,
                'currencySymbol' => $organization->orderSettings?->currency ?? '',
            ];
        }

        $addresses = $customer->addresses()
            ->orderByDesc('is_favorite')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();

        return view('customer.settings.index', compact('customer', 'addresses', 'customerWorkspace'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $customer->update(['name' => trim($validated['name'])]);

        return back()->with('success', 'Имя профиля обновлено.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        $validated = $this->validateAddress($request);

        $validated['customer_id'] = $customer->id;
        $validated['is_default'] = !$customer->addresses()->exists();
        $validated['is_favorite'] = $request->boolean('is_favorite');

        $customer->addresses()->create($validated);

        return back()->with('success', 'Адрес сохранён.');
    }

    public function updateAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless((int) $address->customer_id === (int) $customer->id, 404);

        $validated = $this->validateAddress($request);
        $validated['is_favorite'] = $request->boolean('is_favorite');

        $address->update($validated);

        return back()->with('success', 'Адрес обновлён.');
    }

    public function destroyAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless((int) $address->customer_id === (int) $customer->id, 404);

        $address->delete();

        return back()->with('success', 'Адрес удалён.');
    }

    public function toggleFavoriteAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        abort_unless((int) $address->customer_id === (int) $customer->id, 404);

        $validated = $request->validate([
            'is_favorite' => ['required', 'boolean'],
        ]);

        $address->update(['is_favorite' => (bool) $validated['is_favorite']]);

        return back()->with('success', $address->is_favorite
            ? 'Адрес добавлен в избранное.'
            : 'Адрес удалён из избранного.');
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'address_line' => ['required', 'string', 'max:500'],
            'entrance' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:30'],
            'apartment' => ['nullable', 'string', 'max:30'],
            'intercom' => ['nullable', 'string', 'max:50'],
            'delivery_comment' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
    }
}
