<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationOrderSetting;
use App\Services\CustomerCartService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerCheckoutController extends Controller
{
    private const SESSION_KEY = 'customer_order_cart';
    private const MAX_MONEY_CENTS = 999999999999;

    public function create(Request $request, CustomerCartService $cartService): View|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if (!$customer) {
            return redirect()->guest(route('customer.login'));
        }

        abort_unless($customer->phone_verified_at, 403);

        $replayKey = $request->input('idempotency_key');

        if (is_string($replayKey) && Str::isUuid($replayKey)) {
            $existingOrder = Order::query()
                ->where('customer_id', $customer->id)
                ->where('idempotency_key', $replayKey)
                ->first();

            if ($existingOrder) {
                $cartService->clearForOrganization($customer, (int) $existingOrder->organization_id);
                session()->forget('customer_order_checkout_snapshots');
                session()->forget('customer_order_checkout_snapshot');

                return redirect()
                    ->route('customer.orders.show', $existingOrder)
                    ->with('success', 'Этот заказ уже был оформлен ранее.');
            }
        }

        $sessionCart = session(self::SESSION_KEY, []);
        $cart = $cartService->loadForOrganization(
            $customer,
            (int) ($sessionCart['organization_id'] ?? 0) ?: null
        );
        $organizationId = (int) ($cart['organization_id'] ?? 0);
        $quantities = $cart['items'] ?? [];

        if ($organizationId < 1 || !is_array($quantities) || $quantities === []) {
            return redirect()
                ->route('customer.cart')
                ->with('error', 'Добавьте товары в корзину перед оформлением заказа.');
        }

        $organization = Organization::query()
            ->with('orderSettings')
            ->findOrFail($organizationId);

        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;

        if (!$settings || !$settings->canAcceptOrders()) {
            return redirect()
                ->route('customer.cart')
                ->with('error', 'Организация пока не принимает онлайн-заказы.');
        }

        $items = MenuItem::query()
            ->with('section')
            ->whereIn('id', array_map('intval', array_keys($quantities)))
            ->where('is_active', true)
            ->whereHas('section', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId)
                    ->where('is_active', true);
            })
            ->get()
            ->keyBy('id');

        if ($items->count() !== count($quantities)) {
            return redirect()
                ->route('customer.cart')
                ->with('error', 'Некоторые товары больше недоступны. Обновите корзину.');
        }

        $lines = [];
        $subtotalCents = 0;

        foreach ($quantities as $itemId => $quantity) {
            $item = $items->get((int) $itemId);

            if (!$item || !$item->is_active || (int) $quantity < 1 || (int) $quantity > 99) {
                return redirect()
                    ->route('customer.cart')
                    ->with('error', 'Корзина содержит недоступную позицию или некорректное количество.');
            }

            $unitPriceCents = $this->toCents($item->price);
            $lineTotalCents = $unitPriceCents * (int) $quantity;
            $subtotalCents += $lineTotalCents;

            if (
                $lineTotalCents > self::MAX_MONEY_CENTS
                || $subtotalCents > self::MAX_MONEY_CENTS
            ) {
                return redirect()
                    ->route('customer.cart')
                    ->with('error', 'Сумма заказа превышает допустимый предел.');
            }

            $section = $item->section;

            $lines[] = [
                'item' => $item,
                'quantity' => (int) $quantity,
                'line_total' => $this->fromCents($lineTotalCents),
                'available_delivery' => (bool) (
                    $settings->delivery_enabled
                    && $item->delivery_enabled
                    && $section?->is_active
                    && $section?->delivery_enabled
                ),
                'available_pickup' => (bool) (
                    $settings->pickup_enabled
                    && $item->pickup_enabled
                    && $section?->is_active
                    && $section?->pickup_enabled
                ),
                'available_dine_in' => (bool) (
                    $settings->dine_in_enabled
                    && $section?->is_active
                ),
            ];
        }

        $canUseDelivery = $settings->delivery_enabled
            && collect($lines)->every(fn ($line) => $line['available_delivery']);
        $canUsePickup = $settings->pickup_enabled
            && collect($lines)->every(fn ($line) => $line['available_pickup']);
        $canUseDineIn = $settings->dine_in_enabled
            && collect($lines)->every(fn ($line) => $line['available_dine_in']);

        // Сохраняем прежний приоритет способов, но не выбираем вариант,
        // который недоступен хотя бы для одной позиции корзины.
        $defaultFulfillment = $canUsePickup
            ? Order::FULFILLMENT_PICKUP
            : ($canUseDineIn
                ? Order::FULFILLMENT_DINE_IN
                : ($canUseDelivery ? Order::FULFILLMENT_DELIVERY : null));

        $selectedFulfillment = $request->old('fulfillment_type', $defaultFulfillment);

        $savedAddresses = $customer->addresses()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->orderByDesc('updated_at')
            ->get();

        $defaultAddress = $savedAddresses->firstWhere('is_default', true);

        $deliveryBaseFeeCents = $this->toCents($settings->delivery_fee);

        $minimumOrderCents = $settings->minimum_order !== null
            ? $this->toCents($settings->minimum_order)
            : null;

        $deliveryFeeCents = $minimumOrderCents !== null
            && $subtotalCents >= $minimumOrderCents
                ? 0
                : $deliveryBaseFeeCents;

        if ($subtotalCents + $deliveryFeeCents > self::MAX_MONEY_CENTS) {
            return redirect()
                ->route('customer.cart')
                ->with('error', 'Итоговая сумма заказа превышает допустимый предел.');
        }

        $priceSnapshot = [];
        foreach ($items as $item) {
            $priceSnapshot[$item->id] = (string) $item->price;
        }

        $idempotencyKey = (string) Str::uuid();
        $checkoutSnapshots = session('customer_order_checkout_snapshots', []);

        if (!is_array($checkoutSnapshots)) {
            $checkoutSnapshots = [];
        }

        $snapshotCutoff = now()->subMinutes(30)->timestamp;
        $checkoutSnapshots = array_filter(
            $checkoutSnapshots,
            fn ($snapshot) => is_array($snapshot)
                && (int) ($snapshot['created_at'] ?? 0) >= $snapshotCutoff
        );

        $checkoutSnapshots[$idempotencyKey] = [
            'created_at' => now()->timestamp,
            'organization_id' => $organization->id,
            'prices' => $priceSnapshot,
            'currency' => $settings->currency,
            'delivery_fee' => (string) $settings->delivery_fee,
            'minimum_order' => $settings->minimum_order !== null
                ? (string) $settings->minimum_order
                : null,
        ];

        if (count($checkoutSnapshots) > 10) {
            $checkoutSnapshots = array_slice(
                $checkoutSnapshots,
                -10,
                null,
                true
            );
        }

        session()->put('customer_order_checkout_snapshots', $checkoutSnapshots);

        return view('customer.checkout.index', [
            'customer' => $customer,
            'defaultAddress' => $defaultAddress,
            'savedAddresses' => $savedAddresses,
            'organization' => $organization,
            'settings' => $settings,
            'lines' => $lines,
            'subtotal' => $this->fromCents($subtotalCents),
            'deliveryFee' => $this->fromCents($deliveryFeeCents),
            'deliveryBaseFee' => $this->fromCents($deliveryBaseFeeCents),
            'deliveryTotal' => $this->fromCents($subtotalCents + $deliveryFeeCents),
            'minimumOrder' => $minimumOrderCents !== null
                ? $this->fromCents($minimumOrderCents)
                : null,
            'currencySymbol' => $settings->currency === 'RUB' ? '₽' : $settings->currency,
            'idempotencyKey' => $idempotencyKey,
            'canUseDelivery' => $canUseDelivery,
            'canUsePickup' => $canUsePickup,
            'canUseDineIn' => $canUseDineIn,
            'selectedFulfillment' => $selectedFulfillment,
        ]);
    }

    public function store(Request $request, CustomerCartService $cartService): RedirectResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if (!$customer) {
            return redirect()->guest(route('customer.login'));
        }

        abort_unless($customer->phone_verified_at, 403);

        // POST может повториться после тайм-аута уже успешного оформления.
        // Проверяем ключ до проверки корзины: после первого заказа корзина очищена.
        $idempotencyKey = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
        ])['idempotency_key'];

        $existingOrder = Order::query()
            ->where('customer_id', $customer->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingOrder) {
            $cartService->clearForOrganization($customer, (int) $existingOrder->organization_id);
            session()->forget('customer_order_checkout_snapshots');
            session()->forget('customer_order_checkout_snapshot');

            return redirect()
                ->route('customer.orders.show', $existingOrder)
                ->with('success', 'Этот заказ уже был оформлен ранее.');
        }

        $sessionCart = session(self::SESSION_KEY, []);
        $cart = $cartService->loadForOrganization(
            $customer,
            (int) ($sessionCart['organization_id'] ?? 0) ?: null
        );
        $organizationId = (int) ($cart['organization_id'] ?? 0);
        $quantities = $cart['items'] ?? [];

        if ($organizationId < 1 || !is_array($quantities) || $quantities === []) {
            return redirect()
                ->route('customer.cart')
                ->with('error', 'Корзина пуста.');
        }

        $checkoutSnapshots = session('customer_order_checkout_snapshots', []);
        $checkoutSnapshot = is_array($checkoutSnapshots)
            ? ($checkoutSnapshots[$idempotencyKey] ?? [])
            : [];

        if (
            !is_array($checkoutSnapshot)
            || (int) ($checkoutSnapshot['created_at'] ?? 0) < now()->subMinutes(30)->timestamp
            || (int) ($checkoutSnapshot['organization_id'] ?? 0) !== $organizationId
            || !is_array($checkoutSnapshot['prices'] ?? null)
        ) {
            return redirect()
                ->route('customer.checkout')
                ->with('error', 'Обновите страницу оформления заказа перед подтверждением.');
        }

        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_comment' => ['nullable', 'string', 'max:2000'],
            'fulfillment_type' => ['required', 'in:delivery,pickup,dine_in'],
            'payment_method' => ['required', 'in:cash_on_receipt,card_on_receipt,online'],
            'requested_at' => [
                'nullable',
                'date_format:Y-m-d\TH:i',
                'after:now',
            ],
            'table_number' => ['nullable', 'string', 'max:30'],
            'save_address' => ['nullable', 'boolean'],
            'address_label' => ['required_if:save_address,1', 'nullable', 'string', 'max:50'],
            'address_line' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'max:500'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'entrance' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:30'],
            'apartment' => ['nullable', 'string', 'max:30'],
            'intercom' => ['nullable', 'string', 'max:50'],
            'delivery_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if (
            $validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY
            && !empty($validated['house_number'])
        ) {
            $validated['address_line'] = trim(
                $validated['address_line'] . ', ' . $validated['house_number']
            );
        }

        $organization = Organization::query()
            ->with('orderSettings')
            ->findOrFail($organizationId);

        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;

        if (!$settings || !$settings->canAcceptOrders()) {
            return redirect()
                ->route('customer.cart')
                ->with('error', 'Организация временно не принимает заказы.');
        }

        $fulfillmentEnabled = match ($validated['fulfillment_type']) {
            Order::FULFILLMENT_DELIVERY => $settings->delivery_enabled,
            Order::FULFILLMENT_PICKUP => $settings->pickup_enabled,
            Order::FULFILLMENT_DINE_IN => $settings->dine_in_enabled,
            default => false,
        };

        if (!$fulfillmentEnabled) {
            throw ValidationException::withMessages([
                'fulfillment_type' => 'Выбранный способ получения сейчас недоступен.',
            ]);
        }

        $paymentEnabled = match ($validated['payment_method']) {
            Order::PAYMENT_CASH_ON_RECEIPT => $settings->cash_payment_enabled,
            Order::PAYMENT_CARD_ON_RECEIPT => $settings->card_payment_enabled,
            Order::PAYMENT_ONLINE => false,
            default => false,
        };

        if (!$paymentEnabled) {
            $message = $validated['payment_method'] === Order::PAYMENT_ONLINE
                ? 'Онлайн-оплата станет доступна после подключения платёжного провайдера.'
                : 'Выбранный способ оплаты сейчас недоступен.';

            throw ValidationException::withMessages([
                'payment_method' => $message,
            ]);
        }

        if (
            $validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP
            && empty($validated['requested_at'])
        ) {
            throw ValidationException::withMessages([
                'requested_at' => 'Укажите желаемое время самовывоза.',
            ]);
        }

        if (
            $validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP
        ) {
            $minimumPickupMinutes = max(30, (int) ($settings->pickup_min_minutes ?? 0));
            $minimumPickupTime = Carbon::now('Asia/Krasnoyarsk')->addMinutes($minimumPickupMinutes);
            $requestedAt = Carbon::createFromFormat('Y-m-d\TH:i', $validated['requested_at'], 'Asia/Krasnoyarsk');

            if ($requestedAt->lt($minimumPickupTime)) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Самовывоз доступен не раньше чем через '
                        . $minimumPickupMinutes . ' мин.',
                ]);
            }
        }

        $order = DB::transaction(function () use (
            $customer,
            $organizationId,
            $quantities,
            $checkoutSnapshot,
            $validated
        ) {
            $existingOrder = Order::query()
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                if ((int) $existingOrder->customer_id !== (int) $customer->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Не удалось подтвердить повтор запроса. Обновите страницу оформления.',
                    ]);
                }

                return $existingOrder;
            }

            $customer = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->first();

            if (!$customer || !$customer->phone_verified_at) {
                throw ValidationException::withMessages([
                    'customer_name' => 'Подтвердите номер телефона повторно перед оформлением заказа.',
                ]);
            }

            $organization = Organization::query()
                ->whereKey($organizationId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$organization) {
                throw ValidationException::withMessages([
                    'fulfillment_type' => 'Организация временно недоступна.',
                ]);
            }

            // Повторная проверка после блокировки строки организации.
            // Она сериализует оформление заказов в рамках tenant и закрывает
            // гонку, когда два POST с одним idempotency key пришли одновременно.
            $existingOrder = Order::query()
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                if ((int) $existingOrder->customer_id !== (int) $customer->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Не удалось подтвердить повтор запроса. Обновите страницу оформления.',
                    ]);
                }

                return $existingOrder;
            }

            $settings = OrganizationOrderSetting::query()
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if (!$settings || !$settings->canAcceptOrders()) {
                throw ValidationException::withMessages([
                    'fulfillment_type' => 'Организация временно не принимает заказы.',
                ]);
            }

            $items = MenuItem::query()
                ->whereIn('id', array_map('intval', array_keys($quantities)))
                ->where('is_active', true)
                ->where(function ($query) use ($validated) {
                    if ($validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY) {
                        $query->where('delivery_enabled', true);
                    } elseif ($validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP) {
                        $query->where('pickup_enabled', true);
                    }
                })
                ->whereHas('section', function ($query) use ($organizationId, $validated) {
                    $query->where('organization_id', $organizationId)
                        ->where('is_active', true);

                    if ($validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY) {
                        $query->where('delivery_enabled', true);
                    } elseif ($validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP) {
                        $query->where('pickup_enabled', true);
                    }
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($quantities)) {
                throw ValidationException::withMessages([
                    'cart' => 'Состав меню изменился. Проверьте корзину и повторите оформление.',
                ]);
            }

            $menuSections = MenuSection::query()
                ->whereIn('id', $items->pluck('menu_section_id')->unique()->all())
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($menuSections->count() !== $items->pluck('menu_section_id')->unique()->count()) {
                throw ValidationException::withMessages([
                    'cart' => 'Один из разделов меню больше недоступен. Проверьте корзину.',
                ]);
            }

            if (
                (int) ($checkoutSnapshot['organization_id'] ?? 0) !== $organizationId
                || count($checkoutSnapshot['prices'] ?? []) !== count($quantities)
                || strtoupper((string) ($checkoutSnapshot['currency'] ?? '')) !== strtoupper((string) $settings->currency)
                || $this->toCents($checkoutSnapshot['delivery_fee'] ?? '0') !== $this->toCents($settings->delivery_fee)
                || (
                    (($checkoutSnapshot['minimum_order'] ?? null) === null)
                    !== ($settings->minimum_order === null)
                )
                || (
                    ($checkoutSnapshot['minimum_order'] ?? null) !== null
                    && $settings->minimum_order !== null
                    && $this->toCents($checkoutSnapshot['minimum_order']) !== $this->toCents($settings->minimum_order)
                )
            ) {
                throw ValidationException::withMessages([
                    'cart' => 'Настройки заказа изменились. Обновите страницу оформления и проверьте итоговую сумму.',
                ]);
            }

            foreach ($items as $item) {
                $expectedPrice = $checkoutSnapshot['prices'][$item->id] ?? null;

                if ($expectedPrice === null || (string) $expectedPrice !== (string) $item->price) {
                    throw ValidationException::withMessages([
                        'cart' => 'Цена одного из товаров изменилась. Обновите страницу оформления заказа.',
                    ]);
                }
            }

            $fulfillmentEnabled = match ($validated['fulfillment_type']) {
                Order::FULFILLMENT_DELIVERY => $settings->delivery_enabled,
                Order::FULFILLMENT_PICKUP => $settings->pickup_enabled,
                Order::FULFILLMENT_DINE_IN => $settings->dine_in_enabled,
                default => false,
            };

            if (!$fulfillmentEnabled) {
                throw ValidationException::withMessages([
                    'fulfillment_type' => 'Выбранный способ получения больше недоступен.',
                ]);
            }

            $paymentEnabled = match ($validated['payment_method']) {
                Order::PAYMENT_CASH_ON_RECEIPT => $settings->cash_payment_enabled,
                Order::PAYMENT_CARD_ON_RECEIPT => $settings->card_payment_enabled,
                default => false,
            };

            if (!$paymentEnabled) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Выбранный способ оплаты больше недоступен.',
                ]);
            }

            if (
                $validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP
            ) {
                $minimumPickupMinutes = max(30, (int) ($settings->pickup_min_minutes ?? 0));
                $requestedAt = Carbon::createFromFormat('Y-m-d\TH:i', $validated['requested_at'], 'Asia/Krasnoyarsk');

                if ($requestedAt->lt(Carbon::now('Asia/Krasnoyarsk')->addMinutes($minimumPickupMinutes))) {
                    throw ValidationException::withMessages([
                        'requested_at' => 'Время самовывоза должно быть не раньше чем через '
                            . $minimumPickupMinutes . ' мин.',
                    ]);
                }
            }

            $subtotalCents = 0;
            $orderLines = [];

            foreach ($quantities as $itemId => $quantity) {
                $item = $items->get((int) $itemId);
                $quantity = (int) $quantity;

                if (!$item || $quantity < 1 || $quantity > 99) {
                    throw ValidationException::withMessages([
                        'cart' => 'В корзине обнаружена недопустимая позиция или количество.',
                    ]);
                }

                $unitPriceCents = $this->toCents($item->price);
                $lineTotalCents = $unitPriceCents * $quantity;
                $subtotalCents += $lineTotalCents;

                if (
                    $lineTotalCents > self::MAX_MONEY_CENTS
                    || $subtotalCents > self::MAX_MONEY_CENTS
                ) {
                    throw ValidationException::withMessages([
                        'cart' => 'Сумма заказа превышает допустимый предел.',
                    ]);
                }

                $orderLines[] = [
                    'menu_item_id' => $item->id,
                    'item_name' => $item->name,
                    'item_description' => $item->description,
                    'weight' => $item->weight,
                    'unit' => $item->unit,
                    'unit_price' => $this->fromCents($unitPriceCents),
                    'quantity' => $quantity,
                    'line_total' => $this->fromCents($lineTotalCents),
                ];
            }

            $minimumOrderCents = $settings->minimum_order !== null
                ? $this->toCents($settings->minimum_order)
                : null;

            // minimum_order — порог бесплатной доставки, а не ограничение
            // на оформление заказа. Ниже порога применяется обычная стоимость доставки.
            $deliveryFeeCents = 0;

            if ($validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY) {
                $deliveryFeeCents = $minimumOrderCents !== null
                    && $subtotalCents >= $minimumOrderCents
                        ? 0
                        : $this->toCents($settings->delivery_fee);
            }

            $totalCents = $subtotalCents + $deliveryFeeCents;

            if ($totalCents > self::MAX_MONEY_CENTS) {
                throw ValidationException::withMessages([
                    'cart' => 'Итоговая сумма заказа превышает допустимый предел.',
                ]);
            }

            $deliveryAddress = null;

            if ($validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY) {
                $deliveryAddress = [
                    'address_line' => $validated['address_line'],
                    'entrance' => $validated['entrance'] ?? null,
                    'floor' => $validated['floor'] ?? null,
                    'apartment' => $validated['apartment'] ?? null,
                    'intercom' => $validated['intercom'] ?? null,
                    'recipient_name' => $validated['customer_name'],
                    'recipient_phone' => $customer->phone,
                    'delivery_comment' => $validated['delivery_comment'] ?? null,
                ];
            }

            do {
                $orderNumber = Str::upper(Str::random(12));
            } while (
                Order::query()
                    ->where('organization_id', $organizationId)
                    ->where('order_number', $orderNumber)
                    ->exists()
            );

            $order = Order::create([
                'organization_id' => $organizationId,
                'customer_id' => $customer->id,
                'order_number' => $orderNumber,
                'idempotency_key' => $validated['idempotency_key'],
                'fulfillment_type' => $validated['fulfillment_type'],
                'status' => Order::STATUS_NEW,
                'payment_method' => $validated['payment_method'],
                'payment_status' => Order::PAYMENT_PENDING,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $customer->phone,
                'customer_comment' => $validated['customer_comment'] ?? null,
                'delivery_address' => $deliveryAddress,
                'table_number' => $validated['fulfillment_type'] === Order::FULFILLMENT_DINE_IN
                    ? ($validated['table_number'] ?? null)
                    : null,
                'requested_at' => !empty($validated['requested_at'])
                    ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['requested_at'], 'Asia/Krasnoyarsk')->utc()
                    : null,
                'subtotal' => $this->fromCents($subtotalCents),
                'delivery_fee' => $this->fromCents($deliveryFeeCents),
                'discount_total' => '0.00',
                'total' => $this->fromCents($totalCents),
                'currency' => $settings->currency,
            ]);

            $order->items()->createMany($orderLines);

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => Order::STATUS_NEW,
                'actor_type' => 'customer',
                'actor_id' => $customer->id,
                'comment' => 'Заказ создан клиентом.',
                'created_at' => now(),
            ]);

            $order->payments()->create([
                'provider' => 'offline',
                'provider_payment_id' => null,
                'method' => $validated['payment_method'],
                'status' => Order::PAYMENT_PENDING,
                'amount' => $this->fromCents($totalCents),
                'currency' => $settings->currency,
                'idempotency_key' => $validated['idempotency_key'],
                'metadata' => [
                    'collection' => 'upon_receipt',
                ],
            ]);

            $customer->organizations()->syncWithoutDetaching([
                $organizationId => ['source' => 'order'],
            ]);


            if ($deliveryAddress !== null && !empty($validated['save_address'])) {
                $addressValues = [
                    'label' => trim($validated['address_label']),
                    'recipient_name' => $validated['customer_name'],
                    'recipient_phone' => $customer->phone,
                    'address_line' => $validated['address_line'],
                    'entrance' => $validated['entrance'] ?? null,
                    'floor' => $validated['floor'] ?? null,
                    'apartment' => $validated['apartment'] ?? null,
                    'intercom' => $validated['intercom'] ?? null,
                    'delivery_comment' => $validated['delivery_comment'] ?? null,
                ];

                $savedAddress = $customer->addresses()
                    ->where('address_line', $addressValues['address_line'])
                    ->where('entrance', $addressValues['entrance'])
                    ->where('floor', $addressValues['floor'])
                    ->where('apartment', $addressValues['apartment'])
                    ->where('intercom', $addressValues['intercom'])
                    ->first();

                if ($savedAddress) {
                    $savedAddress->update($addressValues);
                } else {
                    $addressValues['is_default'] = !$customer->addresses()->exists();
                    $customer->addresses()->create($addressValues);
                }
            }

            $customer->update([
                'name' => $validated['customer_name'],
            ]);

            app(\App\Support\OrderNotificationService::class)
                ->notifyManagersAboutNewOrder($order);

            return $order;
        }, 3);

        $cartService->clearForOrganization($customer, (int) $organizationId);
        session()->forget('customer_order_checkout_snapshots');
        session()->forget('customer_order_checkout_snapshot');

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'Заказ успешно оформлен.');
    }

    private function toCents(string|int|float|null $amount): int
    {
        $amount = (string) ($amount ?? '0');
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100)
            + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
