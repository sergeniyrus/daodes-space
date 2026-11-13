@extends('template')

@section('title_page')
    Заказ №{{ $order->order_number }}

<script>
(() => {
    const root = document.querySelector('[data-order-updates-url]');

    if (!root) {
        return;
    }

    const statusLabels = @json($statusLabels);
    const paymentLabels = @json($paymentLabels);
    const statusElement = document.getElementById('customer-order-status');
    const paymentElement = document.getElementById('customer-order-payment-status');
    const historyElement = document.getElementById('customer-order-history');
    const errorElement = document.getElementById('customer-order-update-error');

    let stopped = false;
    let requestInProgress = false;
    let timer = null;

    const terminalStatuses = ['completed', 'cancelled'];

    function formatDate(value) {
        if (!value) {
            return '';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function renderHistory(entries) {
        const fragment = document.createDocumentFragment();

        if (!Array.isArray(entries) || entries.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'customer-order-detail-muted';
            empty.textContent = 'История пока не содержит изменений.';
            fragment.appendChild(empty);
        } else {
            entries.forEach((entry) => {
                const line = document.createElement('div');
                line.className = 'customer-order-detail-line';

                const status = document.createElement('span');
                status.textContent = statusLabels[entry.status] || entry.status;

                const date = document.createElement('span');
                date.className = 'customer-order-detail-muted';
                date.textContent = formatDate(entry.created_at);

                line.append(status, date);
                fragment.appendChild(line);
            });
        }

        historyElement.replaceChildren(fragment);
    }

    async function refreshOrder() {
        if (stopped || requestInProgress || document.hidden) {
            return;
        }

        requestInProgress = true;

        try {
            const response = await fetch(root.dataset.orderUpdatesUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if ([401, 403, 404].includes(response.status)) {
                stopped = true;
                window.clearInterval(timer);
                return;
            }

            if (!response.ok) {
                throw new Error('Order update request failed.');
            }

            const data = await response.json();

            statusElement.textContent = 'Статус: ' + (statusLabels[data.status] || data.status);
            paymentElement.textContent = 'Состояние оплаты: '
                + (paymentLabels[data.payment_status] || data.payment_status);

            renderHistory(data.history);
            errorElement.hidden = true;

            if (terminalStatuses.includes(data.status)) {
                stopped = true;
                window.clearInterval(timer);
            }
        } catch (error) {
            errorElement.hidden = false;
        } finally {
            requestInProgress = false;
        }
    }

    refreshOrder();
    timer = window.setInterval(refreshOrder, 15000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshOrder();
        }
    });
})();
</script>

@endsection

@section('main')
@php
    $statusLabels = [
        'new' => 'Новый',
        'awaiting_payment' => 'Ожидает оплаты',
        'confirmed' => 'Подтверждён',
        'preparing' => 'Готовится',
        'ready' => 'Готов',
        'handed_to_courier' => 'Передан курьеру',
        'in_transit' => 'В пути',
        'issued' => 'Выдан',
        'served' => 'Подан',
        'delivered' => 'Доставлен',
        'completed' => 'Завершён',
        'cancelled' => 'Отменён',
    ];

    $paymentLabels = [
        'pending' => 'Ожидает оплаты',
        'succeeded' => 'Оплачен',
        'failed' => 'Ошибка оплаты',
        'cancelled' => 'Отменён',
        'refunded' => 'Возвращён',
    ];
@endphp

<style>
.customer-order-detail {
    width: min(850px, calc(100% - 24px));
    margin: 28px auto 55px;
    color: #f4f4f4;
}
.customer-order-detail-panel {
    padding: 22px;
    margin-bottom: 16px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .45);
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .35);
}
.customer-order-detail h1 { margin: 0 0 8px; color: #ffd700; }
.customer-order-detail h2 { color: #00e5d4; }
.customer-order-detail-muted { color: #aeb5c4; }
.customer-order-detail-line {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(0, 229, 212, .17);
}
.customer-order-detail-total {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    padding-top: 17px;
    color: #ffd700;
    font-size: 1.2rem;
    font-weight: 700;
}
.customer-order-detail-status { color: #00e5a8; font-weight: 700; }
.customer-order-detail a { color: #00e5d4; text-decoration: none; }
@media(max-width:600px) {
    .customer-order-detail-panel { padding: 16px; }
    .customer-order-detail-line { flex-direction: column; gap: 4px; }
}
</style>

<div class="customer-order-detail" data-order-updates-url="{{ route('customer.orders.updates', $order) }}">
    @include('components.customer-push-subscription')
    @include('components.order-notifications', [
        'notificationsUrl' => route('customer.orders.notifications'),
        'notificationReadUrlTemplate' => route('customer.orders.notifications.read', '__ID__'),
    ])

    @if(session('success'))
        <div class="customer-order-detail-panel customer-order-detail-status">
            {{ session('success') }}
        </div>
    @endif

    <div class="customer-order-detail-panel">
        <h1>Заказ №{{ $order->order_number }}</h1>
        <div class="customer-order-detail-muted">{{ $order->organization->name }}</div>
        <p id="customer-order-status" class="customer-order-detail-status">Статус: {{ $statusLabels[$order->status] ?? $order->status }}</p>
        <p class="customer-order-detail-muted">
            Создан: {{ $order->created_at?->format('d.m.Y H:i') }}
        </p>
        <p class="customer-order-detail-muted">
            Получение:
            @switch($order->fulfillment_type)
                @case('delivery') Доставка @break
                @case('pickup') Самовывоз @break
                @case('dine_in') В заведении @break
                @default {{ $order->fulfillment_type }}
            @endswitch
        </p>
        <p class="customer-order-detail-muted">
            Оплата:
            @switch($order->payment_method)
                @case('cash_on_receipt') Наличные при получении @break
                @case('card_on_receipt') Терминал при получении @break
                @default {{ $order->payment_method }}
            @endswitch
        </p>
        <p id="customer-order-payment-status" class="customer-order-detail-muted">
            Состояние оплаты: {{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}
        </p>
        @if($order->requested_at)
            <p class="customer-order-detail-muted">
                Желаемое время: {{ $order->requested_at->format('d.m.Y H:i') }}
            </p>
        @endif
    </div>

    <div class="customer-order-detail-panel">
        <h2>Состав заказа</h2>
        @foreach($order->items as $item)
            <div class="customer-order-detail-line">
                <span>
                    {{ $item->item_name }} × {{ $item->quantity }}
                    @if($item->weight || $item->unit)
                        <span class="customer-order-detail-muted">
                            ({{ $item->weight }} {{ $item->unit }})
                        </span>
                    @endif
                </span>
                <strong>
                    {{ number_format((float) $item->line_total, 2, ',', ' ') }}
                    {{ $order->currency }}
                </strong>
            </div>
        @endforeach

        <div class="customer-order-detail-line">
            <span>Товары</span>
            <span>{{ number_format((float) $order->subtotal, 2, ',', ' ') }} {{ $order->currency }}</span>
        </div>
        @if((float) $order->delivery_fee > 0)
            <div class="customer-order-detail-line">
                <span>Доставка</span>
                <span>{{ number_format((float) $order->delivery_fee, 2, ',', ' ') }} {{ $order->currency }}</span>
            </div>
        @endif
        <div class="customer-order-detail-total">
            <span>Итого</span>
            <span>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</span>
        </div>
    </div>

    <div class="customer-order-detail-panel">
        <h2>История заказа</h2>
        <div id="customer-order-history">
        @forelse($order->statusHistory as $history)
            <div class="customer-order-detail-line">
                <span>{{ $statusLabels[$history->to_status] ?? $history->to_status }}</span>
                <span class="customer-order-detail-muted">
                    {{ $history->created_at?->format('d.m.Y H:i') }}
                </span>
            </div>
        @empty
            <p class="customer-order-detail-muted">История пока не содержит изменений.</p>
        @endforelse
        </div>
        <p id="customer-order-update-error" class="customer-order-detail-muted" hidden>Не удалось обновить статус. Повторяем попытку…</p>
    </div>

    <div class="customer-order-detail-panel">
        <h2>Контактные данные</h2>
        <p>{{ $order->customer_name }}</p>
        <p>{{ $order->customer_phone }}</p>
        @if($order->delivery_address)
            <p>{{ $order->delivery_address['address_line'] ?? '' }}</p>
            <p class="customer-order-detail-muted">
                {{ $order->delivery_address['entrance'] ?? '' }}
                {{ $order->delivery_address['floor'] ?? '' }}
                {{ $order->delivery_address['apartment'] ?? '' }}
                {{ $order->delivery_address['intercom'] ?? '' }}
            </p>
        @endif
        @if($order->table_number)
            <p>Столик: {{ $order->table_number }}</p>
        @endif
        @if($order->customer_comment)
            <p>Комментарий: {{ $order->customer_comment }}</p>
        @endif
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 18px;">
        <a href="{{ route('customer.catalog', $order->organization) }}">
            ← Вернуться в меню организации
        </a>
        <a href="{{ route('customer.orders.index') }}">
            Мои заказы
        </a>
    </div>
</div>
@endsection
