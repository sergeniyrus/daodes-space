<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCheckoutSnapshot;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationOrderSetting;
use App\Services\CustomerCartService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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

    public function create(Request $request, CustomerCartService $cartService): View|RedirectResponse|JsonResponse
    {
        $customer = $request->expectsJson() ? $request->user() : Auth::guard('customer')->user();

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

                if ($request->expectsJson()) {
                    return response()->json(['data' => $this->mobileOrderPayload($existingOrder)], 200);
                }

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
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['cart' => 'Добавьте товары в корзину перед оформлением заказа.']);
            }

            return redirect()
                ->route('customer.cart')
                ->with('error', 'Добавьте товары в корзину перед оформлением заказа.');
        }

        $organization = Organization::query()
            ->with('orderSettings')
            ->findOrFail($organizationId);

        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;

        if (!$settings || !$settings->hasConfiguredWorkingHours()) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['checkout' => 'Организация ещё не настроила график работы. Оформление заказа временно недоступно.']);
            }

            return redirect()
                ->route('customer.cart')
                ->with('error', 'Организация ещё не настроила график работы. Оформление заказа временно недоступно.');
        }

        if (!$settings->canAcceptOrders()) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['checkout' => 'Организация пока не принимает онлайн-заказы.']);
            }

            return redirect()
                ->route('customer.cart')
                ->with('error', 'Организация пока не принимает онлайн-заказы.');
        }

        $items = MenuItem::query()
            ->with('section')
            ->whereIn('id', array_map('intval', array_keys($quantities)))
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('section', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId)
                    ->where('is_active', true);
            })
            ->get()
            ->keyBy('id');

        if ($items->count() !== count($quantities)) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['cart' => 'Некоторые товары больше недоступны. Обновите корзину.']);
            }

            return redirect()
                ->route('customer.cart')
                ->with('error', 'Некоторые товары больше недоступны. Обновите корзину.');
        }

        $lines = [];
        $subtotalCents = 0;

        foreach ($quantities as $itemId => $quantity) {
            $item = $items->get((int) $itemId);

            if (!$item || !$item->is_active || !$item->is_available || (int) $quantity < 1 || (int) $quantity > 99) {
                if ($request->expectsJson()) {
                    throw ValidationException::withMessages(['cart' => 'Корзина содержит недоступную позицию или некорректное количество.']);
                }

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
                if ($request->expectsJson()) {
                    throw ValidationException::withMessages(['cart' => 'Сумма заказа превышает допустимый предел.']);
                }

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
                    && $item->dine_in_enabled
                    && $section?->is_active
                    && $section?->dine_in_enabled
                ),
            ];
        }

        $canUseDelivery = $settings->hasConfiguredDelivery()
            && ($settings->cash_payment_enabled || $settings->card_payment_enabled)
            && collect($lines)->every(fn ($line) => $line['available_delivery']);
        $canUsePickup = $settings->pickup_enabled
            && $settings->hasPickupAddress()
            && ($settings->cash_payment_enabled || $settings->card_payment_enabled)
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

        $sessionFulfillment = session('customer_order_fulfillment_' . $organization->id);
        $sessionFulfillment = in_array($sessionFulfillment, [
            Order::FULFILLMENT_DELIVERY,
            Order::FULFILLMENT_PICKUP,
            Order::FULFILLMENT_DINE_IN,
        ], true) ? $sessionFulfillment : $defaultFulfillment;

        $selectedFulfillment = $request->old('fulfillment_type', $sessionFulfillment);
        if (!in_array($selectedFulfillment, [
            Order::FULFILLMENT_DELIVERY,
            Order::FULFILLMENT_PICKUP,
            Order::FULFILLMENT_DINE_IN,
        ], true)) {
            $selectedFulfillment = $defaultFulfillment;
        }

        $selectedCanBeUsed = match ($selectedFulfillment) {
            Order::FULFILLMENT_DELIVERY => $canUseDelivery,
            Order::FULFILLMENT_PICKUP => $canUsePickup,
            Order::FULFILLMENT_DINE_IN => $canUseDineIn,
            default => false,
        };
        if (!$selectedCanBeUsed) {
            $selectedFulfillment = $defaultFulfillment;
        }

        $timezone = $settings->timezoneName();
        $slotMinutes = max(5, (int) ($settings->time_slot_minutes ?? 15));
        $requestedAtLimits = [];

        foreach ([
            Order::FULFILLMENT_DELIVERY,
            Order::FULFILLMENT_PICKUP,
            Order::FULFILLMENT_DINE_IN,
        ] as $fulfillmentType) {
            $minimumLeadMinutes = $settings->minimumOrderLeadMinutes($fulfillmentType);
            $minimumOrderAt = Carbon::now($timezone)
                ->addMinutes($minimumLeadMinutes)
                ->ceilUnit('minute');
            $roundingMinutes = ($slotMinutes - ($minimumOrderAt->minute % $slotMinutes)) % $slotMinutes;
            $minimumOrderAt->addMinutes($roundingMinutes)->second(0);

            $requestedAtLimits[$fulfillmentType] = [
                'min' => $minimumOrderAt->format('Y-m-d\\TH:i'),
                'max' => $minimumOrderAt->copy()->addMinutes($slotMinutes)->format('Y-m-d\\TH:i'),
                'lead' => $minimumLeadMinutes,
            ];
        }

        $requestedAtMinimum = $requestedAtLimits[$selectedFulfillment]['min'] ?? $requestedAtLimits[Order::FULFILLMENT_PICKUP]['min'];
        $requestedAtMaximum = $requestedAtLimits[$selectedFulfillment]['max'] ?? $requestedAtLimits[Order::FULFILLMENT_PICKUP]['max'];
        $minimumLeadMinutes = $requestedAtLimits[$selectedFulfillment]['lead'] ?? 30;

        $savedAddresses = $customer->addresses()
            ->orderByDesc('is_favorite')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->orderByDesc('updated_at')
            ->get();

        $defaultAddress = $savedAddresses->firstWhere('is_favorite', true)
            ?? $savedAddresses->firstWhere('is_default', true);

        $deliveryZones = $settings->deliveryZones()->get();
        $deliveryOrigin = [
            'latitude' => $settings->delivery_origin_latitude,
            'longitude' => $settings->delivery_origin_longitude,
        ];

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
            'working_hours' => $settings->working_hours,
            'timezone' => $settings->timezoneName(),
            'preorders_enabled' => (bool) $settings->preorders_enabled,
            'order_lead_minutes' => (int) ($settings->order_lead_minutes ?? 30),
            'pickup_min_minutes' => (int) ($settings->pickup_min_minutes ?? 0),
            'time_slot_minutes' => (int) ($settings->time_slot_minutes ?? 15),
            'pickup_address' => trim((string) $settings->pickup_address),
            'delivery_origin' => $deliveryOrigin,
            'delivery_zones' => $deliveryZones->map(fn ($zone) => [
                'max_distance_km' => (string) $zone->max_distance_km,
                'minimum_order_amount' => (string) $zone->minimum_order_amount,
            ])->values()->all(),
        ];

        if (count($checkoutSnapshots) > 10) {
            $checkoutSnapshots = array_slice(
                $checkoutSnapshots,
                -10,
                null,
                true
            );
        }

        if ($request->expectsJson()) {
            CustomerCheckoutSnapshot::query()
                ->where('customer_id', $customer->id)
                ->where('expires_at', '<', now())
                ->delete();

            CustomerCheckoutSnapshot::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'idempotency_key' => $idempotencyKey],
                [
                    'organization_id' => $organization->id,
                    'payload' => $checkoutSnapshots[$idempotencyKey],
                    'expires_at' => now()->addMinutes(30),
                ]
            );

            return response()->json([
                'data' => [
                    'idempotency_key' => $idempotencyKey,
                    'customer' => ['name' => $customer->name, 'phone' => $customer->phone],
                    'organization' => ['id' => $organization->id, 'name' => $organization->name],
                    'lines' => collect($lines)->map(fn ($line) => [
                        'item_id' => $line['item']->id,
                        'name' => $line['item']->name,
                        'description' => $line['item']->description,
                        'weight' => $line['item']->weight,
                        'unit' => $line['item']->unit,
                        'quantity' => $line['quantity'],
                        'unit_price' => (string) $line['item']->price,
                        'line_total' => $line['line_total'],
                    ])->values(),
                    'subtotal' => $this->fromCents($subtotalCents),
                    'currency' => $settings->currency,
                    'fulfillments' => [
                        'delivery' => $canUseDelivery,
                        'pickup' => $canUsePickup,
                        'dine_in' => $canUseDineIn,
                    ],
                    'selected_fulfillment' => $selectedFulfillment,
                    'requested_at_limits' => $requestedAtLimits,
                    'addresses' => $savedAddresses->map(fn ($address) => [
                        'id' => $address->id,
                        'label' => $address->label,
                        'address_line' => $address->address_line,
                        'house_number' => $address->house_number,
                        'entrance' => $address->entrance,
                        'floor' => $address->floor,
                        'apartment' => $address->apartment,
                        'intercom' => $address->intercom,
                        'delivery_comment' => $address->delivery_comment,
                        'latitude' => $address->latitude,
                        'longitude' => $address->longitude,
                        'is_default' => $address->is_default,
                        'is_favorite' => $address->is_favorite,
                    ])->values(),
                    'delivery_zones' => $deliveryZones->map(fn ($zone) => [
                        'max_distance_km' => (string) $zone->max_distance_km,
                        'minimum_order_amount' => (string) $zone->minimum_order_amount,
                    ])->values(),
                    'delivery_origin' => $deliveryOrigin,
                    'pickup_address' => $settings->pickup_address,
                    'payment_methods' => [
                        'cash_on_receipt' => (bool) $settings->cash_payment_enabled,
                        'card_on_receipt' => (bool) $settings->card_payment_enabled,
                        'guest_account' => (bool) $canUseDineIn,
                        'online' => false,
                    ],
                    'online_payment_unavailable_reason' => 'Онлайн-провайдер ещё не подключён к DAODES.',
                    'requested_at_minimum' => $requestedAtMinimum,
                    'requested_at_maximum' => $requestedAtMaximum,
                ],
            ])->header('Cache-Control', 'private, no-store, max-age=0');
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
            'deliveryZones' => $deliveryZones,
            'deliveryOrigin' => $deliveryOrigin,
            'currencySymbol' => $settings->currency === 'RUB' ? '₽' : $settings->currency,
            'idempotencyKey' => $idempotencyKey,
            'canUseDelivery' => $canUseDelivery,
            'canUsePickup' => $canUsePickup,
            'canUseDineIn' => $canUseDineIn,
            'selectedFulfillment' => $selectedFulfillment,
            'requestedAtMinimum' => $requestedAtMinimum,
            'requestedAtDefault' => $requestedAtMinimum,
            'requestedAtMaximum' => $requestedAtMaximum,
            'minimumLeadMinutes' => $minimumLeadMinutes,
            'requestedAtLimits' => $requestedAtLimits,
        ]);
    }

    public function store(Request $request, CustomerCartService $cartService): RedirectResponse|JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = $request->expectsJson() ? $request->user() : Auth::guard('customer')->user();

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

            if ($request->expectsJson()) {
                return response()->json(['data' => $this->mobileOrderPayload($existingOrder)], 200);
            }

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
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['cart' => 'Корзина пуста.']);
            }

            return redirect()
                ->route('customer.cart')
                ->with('error', 'Корзина пуста.');
        }

        if ($request->expectsJson()) {
            $checkoutSnapshot = CustomerCheckoutSnapshot::query()
                ->where('customer_id', $customer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->where('organization_id', $organizationId)
                ->where('expires_at', '>', now())
                ->value('payload') ?? [];
        } else {
            $checkoutSnapshots = session('customer_order_checkout_snapshots', []);
            $checkoutSnapshot = is_array($checkoutSnapshots)
                ? ($checkoutSnapshots[$idempotencyKey] ?? [])
                : [];
        }

        if (
            !is_array($checkoutSnapshot)
            || (int) ($checkoutSnapshot['created_at'] ?? 0) < now()->subMinutes(30)->timestamp
            || (int) ($checkoutSnapshot['organization_id'] ?? 0) !== $organizationId
            || !is_array($checkoutSnapshot['prices'] ?? null)
        ) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'checkout' => 'Срок проверки корзины истёк. Обновите оформление заказа.',
                ]);
            }

            return redirect()
                ->route('customer.checkout')
                ->with('error', 'Обновите страницу оформления заказа перед подтверждением.');
        }

        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_comment' => ['nullable', 'string', 'max:2000'],
            'fulfillment_type' => ['required', 'in:delivery,pickup,dine_in'],
            'payment_method' => ['required', 'in:cash_on_receipt,card_on_receipt,online,guest_account'],
            'requested_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'table_number' => ['nullable', 'string', 'max:30'],
            'guest_card_identifier' => ['required_if:fulfillment_type,dine_in', 'nullable', 'string', 'max:100'],
            'save_address' => ['nullable', 'boolean'],
            'address_label' => ['required_if:save_address,1', 'nullable', 'string', 'max:50'],
            'address_line' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'max:500'],
            'delivery_latitude' => ['required_if:fulfillment_type,delivery', 'nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['required_if:fulfillment_type,delivery', 'nullable', 'numeric', 'between:-180,180'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'entrance' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:30'],
            'apartment' => ['nullable', 'string', 'max:30'],
            'intercom' => ['nullable', 'string', 'max:50'],
            'delivery_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $fullAddressLine = trim(
            ($validated['address_line'] ?? '')
            . (!empty($validated['house_number']) ? ', ' . $validated['house_number'] : '')
        );

        $organization = Organization::query()
            ->with('orderSettings')
            ->findOrFail($organizationId);

        abort_unless($organization->isActive(), 404);

        $settings = $organization->orderSettings;

        if (!$settings || !$settings->canAcceptOrders()) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['checkout' => 'Организация временно не принимает заказы.']);
            }

            return redirect()
                ->route('customer.cart')
                ->with('error', 'Организация временно не принимает заказы.');
        }

        $fulfillmentEnabled = match ($validated['fulfillment_type']) {
            Order::FULFILLMENT_DELIVERY => $settings->delivery_enabled,
            Order::FULFILLMENT_PICKUP => $settings->pickup_enabled && $settings->hasPickupAddress(),
            Order::FULFILLMENT_DINE_IN => $settings->dine_in_enabled,
            default => false,
        };

        if (!$fulfillmentEnabled) {
            throw ValidationException::withMessages([
                'fulfillment_type' => 'Выбранный способ получения сейчас недоступен.',
            ]);
        }

        if ($validated['fulfillment_type'] === Order::FULFILLMENT_DINE_IN) {
            if ($validated['payment_method'] !== Order::PAYMENT_GUEST_ACCOUNT) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Для заказа в заведении доступен расчёт на выходе по браслету или карте сотрудника.',
                ]);
            }
        } else {
            $paymentEnabled = match ($validated['payment_method']) {
                Order::PAYMENT_CASH_ON_RECEIPT => $settings->cash_payment_enabled,
                Order::PAYMENT_CARD_ON_RECEIPT => $settings->card_payment_enabled,
                Order::PAYMENT_ONLINE, Order::PAYMENT_GUEST_ACCOUNT => false,
                default => false,
            };

            if (!$paymentEnabled) {
                $message = $validated['payment_method'] === Order::PAYMENT_ONLINE
                    ? 'Онлайн-оплата станет доступна после подключения платёжного провайдера.'
                    : 'Выбранный способ оплаты сейчас недоступен.';
                throw ValidationException::withMessages(['payment_method' => $message]);
            }
        }

        $requestedAt = Carbon::createFromFormat('Y-m-d\\TH:i', $validated['requested_at'], $settings->timezoneName());
        if ($requestedAt->lt(Carbon::now($settings->timezoneName())->addMinutes($settings->minimumOrderLeadMinutes($validated['fulfillment_type'])))) {
            throw ValidationException::withMessages([
                'requested_at' => 'Заказ можно запланировать не раньше чем через ' . $settings->minimumOrderLeadMinutes($validated['fulfillment_type']) . ' минут от текущего времени.',
            ]);
        }
        if (((int) $requestedAt->format('i')) % max(5, (int) ($settings->time_slot_minutes ?? 15)) !== 0) {
            throw ValidationException::withMessages([
                'requested_at' => 'Выберите время с шагом ' . $settings->time_slot_minutes . ' минут.',
            ]);
        }
            if (!$settings->isWithinWorkingHours($requestedAt)) {
            throw ValidationException::withMessages([
                'requested_at' => 'Выбранное время находится за пределами графика работы заведения.',
            ]);
        }
        $slotMinutes = max(5, (int) ($settings->time_slot_minutes ?? 15));
        $minimumSlotTime = Carbon::now($settings->timezoneName())
            ->addMinutes($settings->minimumOrderLeadMinutes($validated['fulfillment_type']))
            ->ceilUnit('minute');
        $minimumSlotTime->addMinutes(($slotMinutes - ($minimumSlotTime->minute % $slotMinutes)) % $slotMinutes)->second(0);
        if (!$settings->preorders_enabled && $requestedAt->gt($minimumSlotTime->copy()->addMinutes($slotMinutes))) {
            throw ValidationException::withMessages([
                'requested_at' => 'Предзаказ сейчас отключён. Выберите ближайшее доступное время.',
            ]);
        }

        $order = DB::transaction(function () use (
            $customer,
            $organizationId,
            $quantities,
            $checkoutSnapshot,
            $validated,
            $fullAddressLine
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

            // Повторно проверяем время по заблокированным настройкам:
            // график, часовой пояс или шаг слотов могли измениться после открытия checkout.
            $requestedAt = Carbon::createFromFormat(
                'Y-m-d\\TH:i',
                $validated['requested_at'],
                $settings->timezoneName()
            );
            $minimumLeadMinutes = $settings->minimumOrderLeadMinutes($validated['fulfillment_type']);
            $slotMinutes = max(5, (int) ($settings->time_slot_minutes ?? 15));

            if ($requestedAt->lt(Carbon::now($settings->timezoneName())->addMinutes($minimumLeadMinutes))) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Заказ можно запланировать не раньше чем через ' . $minimumLeadMinutes . ' минут от текущего времени.',
                ]);
            }

            if (((int) $requestedAt->format('i')) % $slotMinutes !== 0) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Выберите время с шагом ' . $slotMinutes . ' минут.',
                ]);
            }

            if (!$settings->isWithinWorkingHours($requestedAt)) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Выбранное время находится за пределами графика работы заведения.',
                ]);
            }

            $minimumSlotTime = Carbon::now($settings->timezoneName())
                ->addMinutes($minimumLeadMinutes)
                ->ceilUnit('minute');
            $minimumSlotTime->addMinutes(($slotMinutes - ($minimumSlotTime->minute % $slotMinutes)) % $slotMinutes)->second(0);

            if (
                !$settings->preorders_enabled
                && $requestedAt->gt($minimumSlotTime->copy()->addMinutes($slotMinutes))
            ) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Предзаказ сейчас отключён. Выберите ближайшее доступное время.',
                ]);
            }

            $items = MenuItem::query()
                ->whereIn('id', array_map('intval', array_keys($quantities)))
                ->where('is_active', true)
                ->where('is_available', true)
                ->where(function ($query) use ($validated) {
                    if ($validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY) {
                        $query->where('delivery_enabled', true);
                    } elseif ($validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP) {
                        $query->where('pickup_enabled', true);
                    } elseif ($validated['fulfillment_type'] === Order::FULFILLMENT_DINE_IN) {
                        $query->where('dine_in_enabled', true);
                    }
                })
                ->whereHas('section', function ($query) use ($organizationId, $validated) {
                    $query->where('organization_id', $organizationId)
                        ->where('is_active', true);

                    if ($validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY) {
                        $query->where('delivery_enabled', true);
                    } elseif ($validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP) {
                        $query->where('pickup_enabled', true);
                    } elseif ($validated['fulfillment_type'] === Order::FULFILLMENT_DINE_IN) {
                        $query->where('dine_in_enabled', true);
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
                || ($checkoutSnapshot['working_hours'] ?? null) !== $settings->working_hours
                || ($checkoutSnapshot['timezone'] ?? null) !== $settings->timezoneName()
                || (bool) ($checkoutSnapshot['preorders_enabled'] ?? false) !== (bool) $settings->preorders_enabled
                || (int) ($checkoutSnapshot['order_lead_minutes'] ?? 0) !== (int) ($settings->order_lead_minutes ?? 30)
                || (int) ($checkoutSnapshot['pickup_min_minutes'] ?? 0) !== (int) ($settings->pickup_min_minutes ?? 0)
                || (int) ($checkoutSnapshot['time_slot_minutes'] ?? 0) !== (int) ($settings->time_slot_minutes ?? 15)
                || ($validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP && ($checkoutSnapshot['pickup_address'] ?? null) !== trim((string) $settings->pickup_address))
                || ($checkoutSnapshot['delivery_origin'] ?? null) !== [
                    'latitude' => $settings->delivery_origin_latitude,
                    'longitude' => $settings->delivery_origin_longitude,
                ]
                || ($checkoutSnapshot['delivery_zones'] ?? []) !== $settings->deliveryZones()
                    ->get()
                    ->map(fn ($zone) => [
                        'max_distance_km' => (string) $zone->max_distance_km,
                        'minimum_order_amount' => (string) $zone->minimum_order_amount,
                    ])->values()->all()
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
                Order::FULFILLMENT_DELIVERY => $settings->hasConfiguredDelivery(),
                Order::FULFILLMENT_PICKUP => $settings->pickup_enabled && $settings->hasPickupAddress(),
                Order::FULFILLMENT_DINE_IN => $settings->dine_in_enabled,
                default => false,
            };

            if (!$fulfillmentEnabled) {
                throw ValidationException::withMessages([
                    'fulfillment_type' => 'Выбранный способ получения больше недоступен.',
                ]);
            }

            if ($validated['fulfillment_type'] === Order::FULFILLMENT_DINE_IN) {
                if ($validated['payment_method'] !== Order::PAYMENT_GUEST_ACCOUNT) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Для заказа в заведении используется расчёт на выходе.',
                    ]);
                }
            } else {
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
            }

            $requestedAt = Carbon::createFromFormat('Y-m-d\\TH:i', $validated['requested_at'], $settings->timezoneName());
            if ($requestedAt->lt(Carbon::now($settings->timezoneName())->addMinutes($settings->minimumOrderLeadMinutes($validated['fulfillment_type'])))) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Заказ можно запланировать не раньше чем через ' . $settings->minimumOrderLeadMinutes($validated['fulfillment_type']) . ' минут от текущего времени.',
                ]);
            }
            if (((int) $requestedAt->format('i')) % max(5, (int) ($settings->time_slot_minutes ?? 15)) !== 0) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Выберите время с шагом ' . $settings->time_slot_minutes . ' минут.',
                ]);
            }
            if (!$settings->isWithinWorkingHours($requestedAt)) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Выбранное время находится за пределами графика работы заведения.',
                ]);
            }
            $slotMinutes = max(5, (int) ($settings->time_slot_minutes ?? 15));
            $minimumSlotTime = Carbon::now($settings->timezoneName())
                ->addMinutes($settings->minimumOrderLeadMinutes($validated['fulfillment_type']))
                ->ceilUnit('minute');
            $minimumSlotTime->addMinutes(($slotMinutes - ($minimumSlotTime->minute % $slotMinutes)) % $slotMinutes)->second(0);
            if (!$settings->preorders_enabled && $requestedAt->gt($minimumSlotTime->copy()->addMinutes($slotMinutes))) {
                throw ValidationException::withMessages([
                    'requested_at' => 'Предзаказ сейчас отключён. Выберите ближайшее доступное время.',
                ]);
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

            $deliveryFeeCents = 0;
            $deliveryDistanceKm = null;
            $deliveryZone = null;

            if ($validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY) {
                if (
                    $settings->delivery_origin_latitude === null
                    || $settings->delivery_origin_longitude === null
                ) {
                    throw ValidationException::withMessages([
                        'fulfillment_type' => 'Организация ещё не настроила точку отправления доставки.',
                    ]);
                }

                $distanceKm = $this->calculateDistanceKm(
                    (float) $settings->delivery_origin_latitude,
                    (float) $settings->delivery_origin_longitude,
                    (float) $validated['delivery_latitude'],
                    (float) $validated['delivery_longitude']
                );
                $deliveryDistanceKm = $distanceKm;

                $deliveryZone = $settings->deliveryZones()
                    ->where('max_distance_km', '>=', $distanceKm)
                    ->orderBy('max_distance_km')
                    ->first();

                if (!$deliveryZone || $distanceKm > 10) {
                    throw ValidationException::withMessages([
                        'address_line' => 'Адрес находится за пределами зоны доставки (максимум 10 км от заведения).',
                    ]);
                }

                $zoneMinimumCents = $this->toCents($deliveryZone->minimum_order_amount);
                if ($subtotalCents < $zoneMinimumCents) {
                    throw ValidationException::withMessages([
                        'cart' => 'Для доставки на расстояние ' . number_format($distanceKm, 2, ',', ' ')
                            . ' км минимальная сумма корзины составляет '
                            . number_format((float) $deliveryZone->minimum_order_amount, 2, ',', ' ')
                            . ' ' . $settings->currency . '.',
                    ]);
                }
            }

            $totalCents = $subtotalCents;

            if ($totalCents > self::MAX_MONEY_CENTS) {
                throw ValidationException::withMessages([
                    'cart' => 'Итоговая сумма заказа превышает допустимый предел.',
                ]);
            }

            $deliveryAddress = null;

            if ($validated['fulfillment_type'] === Order::FULFILLMENT_DELIVERY) {
                $deliveryAddress = [
                    'address_line' => $fullAddressLine,
                    'house_number' => $validated['house_number'] ?? null,
                    'latitude' => (float) $validated['delivery_latitude'],
                    'longitude' => (float) $validated['delivery_longitude'],
                    'distance_km' => round((float) $deliveryDistanceKm, 3),
                    'zone_max_distance_km' => (string) $deliveryZone->max_distance_km,
                    'zone_minimum_order_amount' => (string) $deliveryZone->minimum_order_amount,
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
                'guest_card_identifier' => $validated['fulfillment_type'] === Order::FULFILLMENT_DINE_IN
                    ? trim($validated['guest_card_identifier'])
                    : null,
                'pickup_address_snapshot' => $validated['fulfillment_type'] === Order::FULFILLMENT_PICKUP
                    ? trim((string) $settings->pickup_address)
                    : null,
                'requested_at' => !empty($validated['requested_at'])
                    ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['requested_at'], $settings->timezoneName())->utc()
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
                    'collection' => $validated['fulfillment_type'] === Order::FULFILLMENT_DINE_IN ? 'guest_account_at_venue' : 'upon_receipt',
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
                    'house_number' => $validated['house_number'] ?? null,
                    'entrance' => $validated['entrance'] ?? null,
                    'floor' => $validated['floor'] ?? null,
                    'apartment' => $validated['apartment'] ?? null,
                    'intercom' => $validated['intercom'] ?? null,
                    'delivery_comment' => $validated['delivery_comment'] ?? null,
                    'latitude' => (float) $validated['delivery_latitude'],
                    'longitude' => (float) $validated['delivery_longitude'],
                ];

                $savedAddress = $customer->addresses()
                    ->where('address_line', $addressValues['address_line'])
                    ->where('house_number', $addressValues['house_number'])
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

        if ($request->expectsJson()) {
            CustomerCheckoutSnapshot::query()
                ->where('customer_id', $customer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->delete();

            return response()->json(['data' => $this->mobileOrderPayload($order)], 201)
                ->header('Cache-Control', 'private, no-store, max-age=0');
        }

        session()->forget('customer_order_checkout_snapshots');
        session()->forget('customer_order_checkout_snapshot');

        return redirect()
            ->route('customer.orders.show', $order)
            ->with('success', 'Заказ успешно оформлен.');
    }

    private function mobileOrderPayload(Order $order): array
    {
        $order->loadMissing(['organization', 'items', 'statusHistory', 'payments']);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'organization' => ['id' => $order->organization?->id, 'name' => $order->organization?->name],
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'fulfillment_type' => $order->fulfillment_type,
            'requested_at' => $order->requested_at?->toIso8601String(),
            'subtotal' => $order->subtotal,
            'delivery_fee' => $order->delivery_fee,
            'discount_total' => $order->discount_total,
            'total' => $order->total,
            'currency' => $order->currency,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->values(),
        ];
    }

    private function calculateDistanceKm(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo): float
    {
        $earthRadiusKm = 6371.0088;
        $latitudeDelta = deg2rad($latitudeTo - $latitudeFrom);
        $longitudeDelta = deg2rad($longitudeTo - $longitudeFrom);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeFrom))
            * cos(deg2rad($latitudeTo))
            * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
