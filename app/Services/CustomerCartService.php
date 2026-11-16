<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCart;

class CustomerCartService
{
    public const SESSION_KEY = 'customer_order_cart';

    /**
     * Загружает корзину клиента для организации и один раз объединяет
     * её с гостевой корзиной текущей сессии.
     */
    public function loadForOrganization(Customer $customer, ?int $organizationId = null): array
    {
        $sessionCart = session(self::SESSION_KEY, []);

        $organizationId = $organizationId ?: (int) ($sessionCart['organization_id'] ?? 0);

        if ($organizationId < 1) {
            $latestCart = CustomerCart::query()
                ->where('customer_id', $customer->id)
                ->latest('updated_at')
                ->first();

            $organizationId = (int) ($latestCart?->organization_id ?? 0);
        }

        if ($organizationId < 1) {
            return ['organization_id' => 0, 'items' => []];
        }

        $savedCart = CustomerCart::query()
            ->where('customer_id', $customer->id)
            ->where('organization_id', $organizationId)
            ->first();

        $syncKey = $this->syncKey($customer, $organizationId);
        $sessionMatches = (int) ($sessionCart['organization_id'] ?? 0) === $organizationId;
        $items = $savedCart?->items ?? [];

        if (!is_array($items)) {
            $items = [];
        }

        if ($sessionMatches && !session()->get($syncKey, false)) {
            foreach (($sessionCart['items'] ?? []) as $itemId => $quantity) {
                $itemId = (int) $itemId;
                $quantity = (int) $quantity;

                if ($itemId > 0 && $quantity > 0) {
                    $items[$itemId] = min(99, (int) ($items[$itemId] ?? 0) + $quantity);
                }
            }
        }

        $cart = [
            'organization_id' => $organizationId,
            'items' => $this->normalizeItems($items),
        ];

        $this->persist($customer, $cart);
        session()->put(self::SESSION_KEY, $cart);
        session()->put($syncKey, true);

        return $cart;
    }

    /**
     * Сохраняет изменение одновременно в сессии и в базе для авторизованного клиента.
     */
    public function save(?Customer $customer, array $cart): void
    {
        $organizationId = (int) ($cart['organization_id'] ?? 0);
        $normalized = [
            'organization_id' => $organizationId,
            'items' => $this->normalizeItems($cart['items'] ?? []),
        ];

        if ($organizationId < 1 || $normalized['items'] === []) {
            if ($customer && $organizationId > 0) {
                CustomerCart::query()
                    ->where('customer_id', $customer->id)
                    ->where('organization_id', $organizationId)
                    ->delete();

                session()->put($this->syncKey($customer, $organizationId), true);
            }

            session()->forget(self::SESSION_KEY);

            return;
        }

        session()->put(self::SESSION_KEY, $normalized);

        if ($customer) {
            $this->persist($customer, $normalized);
            session()->put($this->syncKey($customer, $organizationId), true);
        }
    }

    public function clearForOrganization(?Customer $customer, int $organizationId): void
    {
        if ($customer && $organizationId > 0) {
            CustomerCart::query()
                ->where('customer_id', $customer->id)
                ->where('organization_id', $organizationId)
                ->delete();

            session()->put($this->syncKey($customer, $organizationId), true);
        }

        $sessionCart = session(self::SESSION_KEY, []);

        if (
            $organizationId < 1
            || (int) ($sessionCart['organization_id'] ?? 0) === $organizationId
        ) {
            session()->forget(self::SESSION_KEY);
        }
    }

    public function latestOrganizationId(Customer $customer): ?int
    {
        $organizationId = CustomerCart::query()
            ->where('customer_id', $customer->id)
            ->latest('updated_at')
            ->value('organization_id');

        return $organizationId ? (int) $organizationId : null;
    }

    private function persist(Customer $customer, array $cart): void
    {
        if ($cart['items'] === []) {
            CustomerCart::query()
                ->where('customer_id', $customer->id)
                ->where('organization_id', $cart['organization_id'])
                ->delete();

            return;
        }

        CustomerCart::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'organization_id' => $cart['organization_id'],
            ],
            ['items' => $cart['items']]
        );
    }

    private function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $itemId => $quantity) {
            $itemId = (int) $itemId;
            $quantity = (int) $quantity;

            if ($itemId > 0 && $quantity > 0) {
                $normalized[$itemId] = min(99, $quantity);
            }
        }

        return $normalized;
    }

    private function syncKey(Customer $customer, int $organizationId): string
    {
        return 'customer_order_cart_synced_' . $customer->id . '_' . $organizationId;
    }
}
