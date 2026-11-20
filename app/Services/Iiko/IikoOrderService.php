<?php

namespace App\Services\Iiko;

use App\Models\IikoConnection;
use App\Models\IikoMenuMapping;
use App\Models\Order;
use RuntimeException;

class IikoOrderService
{
    public function __construct(
        private readonly IikoCloudClient $client,
        private readonly IikoAvailabilityService $availability,
        private readonly IikoSyncLogger $logger
    ) {
    }

    /**
     * Send a DAODES pickup or delivery order to iiko.
     *
     * Dine-in is intentionally rejected until DAODES table numbers and guest
     * card identifiers are explicitly mapped to iiko table/guest entities.
     */
    public function send(Order $order, IikoConnection $connection): array
    {
        if (!in_array($order->fulfillment_type, [Order::FULFILLMENT_DELIVERY, Order::FULFILLMENT_PICKUP], true)) {
            throw new RuntimeException('Передача заказов в заведении требует отдельного сопоставления столов iiko.');
        }

        if (!$connection->iiko_organization_id || !$connection->terminal_group_id) {
            throw new RuntimeException('Не выбраны организация и терминальная группа iiko.');
        }

        $stoppedMenuItemIds = $this->availability->stoppedMenuItemIds((int) $order->organization_id);
        $orderMenuItemIds = $order->items->pluck('menu_item_id')->map(fn ($id) => (int) $id)->all();

        if (array_intersect($stoppedMenuItemIds, $orderMenuItemIds) !== []) {
            throw new RuntimeException('В заказе есть блюдо, которое попало в актуальный стоп-лист iiko.');
        }

        if (!str_starts_with(trim((string) $order->customer_phone), '+')) {
            throw new RuntimeException('Телефон клиента для iiko должен быть в международном формате и начинаться с +.');
        }

        $mappings = IikoMenuMapping::query()
            ->where('organization_id', $order->organization_id)
            ->where('iiko_organization_id', $connection->iiko_organization_id)
            ->where('external_menu_id', $connection->external_menu_id)
            ->where('price_category_id', $connection->price_category_id)
            ->whereIn('menu_item_id', $orderMenuItemIds)
            ->get()
            ->keyBy('menu_item_id');

        $iikoItems = [];

        foreach ($order->items as $item) {
            $mapping = $mappings->get((int) $item->menu_item_id);

            if (!$mapping) {
                throw new RuntimeException('Не сопоставлено блюдо DAODES: ' . $item->item_name . '.');
            }

            $iikoItems[] = [
                'type' => 'Product',
                'productId' => $mapping->iiko_product_id,
                'amount' => (float) $item->quantity,
            ];
        }

        $orderPayload = [
            'id' => $order->idempotency_key,
            'phone' => $order->customer_phone,
            'customer' => [
                'type' => 'one-time',
                'name' => $order->customer_name,
            ],
            'orderServiceType' => $order->fulfillment_type === Order::FULFILLMENT_DELIVERY
                ? 'DeliveryByCourier'
                : 'DeliveryByClient',
            'items' => $iikoItems,
        ];

        // iiko expects local terminal time without a timezone offset.
        // DAODES already stores the organization's timezone in order settings.
        if ($order->requested_at) {
            $timezone = $order->organization?->orderSettings?->timezoneName()
                ?? (string) config('app.timezone', 'UTC');

            $orderPayload['completeBefore'] = $order->requested_at
                ->copy()
                ->setTimezone($timezone)
                ->format('Y-m-d H:i:s.v');
        }

        if (filled($order->customer_comment)) {
            $orderPayload['comment'] = $order->customer_comment;
        }

        if ($order->fulfillment_type === Order::FULFILLMENT_DELIVERY) {
            $address = is_array($order->delivery_address) ? $order->delivery_address : [];
            $addressLine = trim((string) ($address['address_line'] ?? ''));

            if ($addressLine === '') {
                throw new RuntimeException('Для заказа доставки не указан адрес.');
            }

            if (mb_strtolower((string) $connection->address_format_type) !== 'city') {
                throw new RuntimeException(
                    'Формат адреса iiko (' . ($connection->address_format_type ?: 'не определён')
                    . ') пока не сопоставлен. Передача доставки остановлена во избежание некорректного адреса.'
                );
            }

            $deliveryAddress = [
                'type' => 'city',
                'line1' => mb_substr($addressLine, 0, 250),
            ];

            foreach ([
                'flat' => $address['apartment'] ?? null,
                'entrance' => $address['entrance'] ?? null,
                'floor' => $address['floor'] ?? null,
                'doorphone' => $address['intercom'] ?? null,
            ] as $field => $value) {
                if (filled($value)) {
                    $deliveryAddress[$field] = (string) $value;
                }
            }

            $orderPayload['deliveryPoint'] = ['address' => $deliveryAddress];

            if (filled($address['delivery_comment'] ?? null)) {
                $orderPayload['deliveryPoint']['comment'] = (string) $address['delivery_comment'];
            }
        }

        $request = [
            'organizationId' => $connection->iiko_organization_id,
            'terminalGroupId' => $connection->terminal_group_id,
            'order' => $orderPayload,
        ];

        $timezone = $order->organization?->orderSettings?->timezoneName()
            ?? (string) config('app.timezone', 'UTC');

        $deliveryDate = $order->requested_at
            ? $order->requested_at->copy()->setTimezone($timezone)->format('Y-m-d H:i:s.v')
            : now($timezone)->format('Y-m-d H:i:s.v');

        $restrictionPayload = $this->client->request(
            $connection,
            'POST',
            '/api/1/delivery_restrictions/allowed',
            [
                'organizationId' => $connection->iiko_organization_id,
                'isCourierDelivery' => $order->fulfillment_type === Order::FULFILLMENT_DELIVERY,
                'deliveryDate' => $deliveryDate,
                'orderItems' => collect($iikoItems)->map(fn ($item) => [
                    'id' => $item['productId'],
                    'product' => $item['productId'],
                    'amount' => $item['amount'],
                ])->all(),
                'deliverySum' => (float) $order->subtotal,
                'discountSum' => (float) $order->discount_total,
            ]
        );

        $isAllowed = data_get($restrictionPayload, 'isAllowed')
            ?? data_get($restrictionPayload, 'allowed')
            ?? data_get($restrictionPayload, 'deliveryIsAllowed');

        if ($isAllowed === false) {
            throw new RuntimeException('iiko не разрешает доставку или самовывоз на выбранное время.');
        }

        $response = $this->client->request(
            $connection,
            'POST',
            '/api/1/deliveries/create',
            $request
        );

        $this->logger->record(
            $connection,
            'order_send',
            'success',
            'Заказ DAODES передан в iiko.',
            $response['correlationId'] ?? null,
            ['order_id' => $order->id, 'endpoint' => '/api/1/deliveries/create']
        );

        return $response;
    }

    public function commandStatus(IikoConnection $connection, string $correlationId): array
    {
        return $this->client->request($connection, 'POST', '/api/1/commands/status', [
            'organizationId' => $connection->iiko_organization_id,
            'correlationId' => $correlationId,
        ]);
    }
}
