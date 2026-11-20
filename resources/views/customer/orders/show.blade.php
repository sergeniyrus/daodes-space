@extends('customer.layouts.workspace')

@section('title_page')
    Заказ №{{ $order->order_number }}



@endsection

@section('customer-content')
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
.customer-order-live-banner {
    position: relative;
    display: flex;
    align-items: center;
    gap: 15px;
    overflow: hidden;
    padding: 20px 22px;
    margin-bottom: 16px;
    background: linear-gradient(115deg, rgba(0, 229, 168, .18), rgba(0, 229, 212, .07) 58%, rgba(255, 215, 0, .08));
    border: 1px solid rgba(0, 229, 168, .72);
    border-radius: 18px;
    box-shadow: 0 0 28px rgba(0, 229, 168, .12), inset 0 0 22px rgba(0, 229, 168, .04);
}
.customer-order-live-banner[hidden] { display: none; }
.customer-order-live-indicator {
    position: relative;
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    color: #061711;
    background: #00e5a8;
    border-radius: 50%;
    font-size: 1.35rem;
    box-shadow: 0 0 0 7px rgba(0, 229, 168, .1);
    animation: customer-order-pulse 1.8s ease-out infinite;
}
.customer-order-live-title { margin: 0 0 5px; color: #00ffc2; font-size: 1.2rem; font-weight: 900; }
.customer-order-live-text { color: #e3fff6; line-height: 1.45; }
.customer-order-live-banner.is-delivered {
    background: linear-gradient(115deg, rgba(255, 215, 0, .16), rgba(0, 229, 212, .06));
    border-color: rgba(255, 215, 0, .65);
}
.customer-order-live-banner.is-delivered .customer-order-live-indicator {
    background: #ffd700;
    animation: none;
}
@keyframes customer-order-pulse {
    0% { box-shadow: 0 0 0 0 rgba(0, 229, 168, .35); }
    70% { box-shadow: 0 0 0 12px rgba(0, 229, 168, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 229, 168, 0); }
}
.customer-order-detail a { color: #00e5d4; text-decoration: none; }
@media(max-width:600px) {
    .customer-order-detail-panel { padding: 16px; }
    .customer-order-detail-line { flex-direction: column; gap: 4px; }
}
</style>

<div
    class="customer-order-detail"
    data-order-updates-url="{{ route('customer.orders.updates', $order) }}"
    data-order-status="{{ $order->status }}"
    data-payment-status="{{ $order->payment_status }}"
>
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
        <section
            id="customer-order-live-banner"
            class="customer-order-live-banner"
            aria-live="polite"
            @if($order->status !== 'in_transit' && $order->status !== 'delivered') hidden @endif
        >
            <div class="customer-order-live-indicator" id="customer-order-live-icon">
                {{ $order->status === 'delivered' ? '✓' : '➤' }}
            </div>
            <div>
                <div class="customer-order-live-title" id="customer-order-live-title">
                    @if($order->status === 'in_transit')
                        Сейчас курьер едет к вам!
                    @elseif($order->status === 'delivered')
                        Заказ доставлен
                    @endif
                </div>
                <div class="customer-order-live-text" id="customer-order-live-text">
                    @if($order->status === 'in_transit')
                        Курьер уже забрал ваш заказ и направляется по указанному адресу.
                    @elseif($order->status === 'delivered')
                        Приятного аппетита! Заказ передан вам.
                    @endif
                </div>
            </div>
        </section>
        <p class="customer-order-detail-muted">
            Создан: {{ $order->created_at?->timezone($order->organization?->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') }}
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
        @if($order->fulfillment_type === 'pickup')
            <p class="customer-order-detail-muted">
                Адрес самовывоза: {{ $order->pickup_address_snapshot ?: ($order->organization?->orderSettings?->pickup_address ?? 'Уточните адрес у заведения') }}
            </p>
        @endif
        <p class="customer-order-detail-muted">
            Оплата:
            @switch($order->payment_method)
                @case('cash_on_receipt') Наличные при получении @break
                @case('card_on_receipt') Терминал при получении @break
                @case('guest_account') На счёт гостя — расчёт при выходе @break
                @default {{ $order->payment_method }}
            @endswitch
        </p>
        <p id="customer-order-payment-status" class="customer-order-detail-muted">
            Состояние оплаты: {{ $order->payment_method === 'guest_account' && $order->payment_status === 'pending' ? 'Ожидает расчёта на кассе при выходе' : ($paymentLabels[$order->payment_status] ?? $order->payment_status) }}
        </p>
        @if($order->fulfillment_type === 'pickup')
            <div id="customer-pickup-action" style="margin-top: 15px;" @if($order->status !== 'ready') hidden @endif>
                <form method="POST" action="{{ route('customer.orders.pickup.confirm', $order) }}" onsubmit="return confirm('Подтвердить, что вы забрали заказ?');">
                    @csrf
                    <button type="submit" style="width:100%; min-height:48px; padding:10px 16px; color:#00e5d4; background:rgba(0,229,212,.07); border:1px solid rgba(0,229,212,.65); border-radius:12px; font-weight:800; cursor:pointer;">
                        Я забрал заказ
                    </button>
                </form>
                <p class="customer-order-detail-muted" style="margin-bottom:0;">Заказ готов. Заберите его и подтвердите получение.</p>
            </div>
        @endif
        @if($order->requested_at)
            <p class="customer-order-detail-muted">
                Желаемое время: {{ $order->requested_at->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') }}
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
                    {{ $history->created_at?->timezone($order->organization?->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') }}
                </span>
            </div>
        @empty
            <p class="customer-order-detail-muted">История пока не содержит изменений.</p>
        @endforelse
        </div>
        <p id="customer-order-update-error" class="customer-order-detail-muted" hidden>Не удалось обновить статус. Повторяем попытку…</p>
    </div>

    @if(in_array($order->status, ['issued', 'served', 'delivered', 'completed'], true))
        <div class="customer-order-detail-panel">
            <h2>Оценка заказа</h2>
            @if($order->review)
                <p style="color:#ffd700;font-size:1.25rem">{{ str_repeat('★', $order->review->rating) }}{{ str_repeat('☆', 5 - $order->review->rating) }}</p>
                <p class="customer-order-detail-muted">Вы уже оценили этот заказ. Оценку можно изменить.</p>
            @else
                <p class="customer-order-detail-muted">Расскажите, как прошёл заказ. Оценить его можно только после получения.</p>
            @endif
            <form method="POST" action="{{ route('customer.orders.review.store', $order) }}">
                @csrf
                <label for="order-review-rating" style="display:block;margin:12px 0 6px;color:#00e5d4">Ваша оценка</label>
                <select id="order-review-rating" name="rating" required style="width:100%;padding:11px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.4);border-radius:9px">
                    @for($rating = 5; $rating >= 1; $rating--)
                        <option value="{{ $rating }}" @selected((int) old('rating', $order->review?->rating ?? 5) === $rating)>{{ $rating }} из 5 {{ str_repeat('★', $rating) }}</option>
                    @endfor
                </select>
                <label for="order-review-comment" style="display:block;margin:12px 0 6px;color:#00e5d4">Комментарий</label>
                <textarea id="order-review-comment" name="comment" rows="4" maxlength="3000" style="width:100%;padding:11px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.4);border-radius:9px">{{ old('comment', $order->review?->comment) }}</textarea>
                <button type="submit" style="margin-top:12px;padding:11px 18px;color:#071416;background:#00e5d4;border:0;border-radius:9px;font-weight:800;cursor:pointer">Сохранить оценку</button>
            </form>
        </div>
    @endif

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
            <p>Номер столика: {{ $order->table_number }}</p>
        @endif
        @if($order->guest_card_identifier)
            <p>Браслет / карта сотрудника: {{ $order->guest_card_identifier }}</p>
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
    const liveBanner = document.getElementById('customer-order-live-banner');
    const liveTitle = document.getElementById('customer-order-live-title');
    const liveText = document.getElementById('customer-order-live-text');
    const liveIcon = document.getElementById('customer-order-live-icon');
    const pickupAction = document.getElementById('customer-pickup-action');
    let lastKnownStatus = root.dataset.orderStatus || '';
    let lastKnownPaymentStatus = root.dataset.paymentStatus || '';

    function playCustomerOrderSound() {
        // Используем общий обработчик Firebase, если он уже зарегистрирован.
        if (typeof window.playDaodesCustomerNotificationSound === 'function') {
            window.playDaodesCustomerNotificationSound();
            return;
        }

        // Резервный вариант, если Firebase push-компонент недоступен.
        const now = Date.now();
        if (window.__daodesCustomerNotificationSoundAt && now - window.__daodesCustomerNotificationSoundAt < 2000) return;
        window.__daodesCustomerNotificationSoundAt = now;

        try {
            const audio = new Audio('/sounds/notification.mp3');
            audio.preload = 'auto';
            audio.play().catch(function(error) {
                console.debug('Customer order sound was blocked:', error);
            });
        } catch (error) {
            console.warn('Customer order sound:', error);
        }
    }

    function renderDeliveryStatus(status) {
        if (!liveBanner || !liveTitle || !liveText || !liveIcon) {
            return;
        }

        liveBanner.hidden = true;
        liveBanner.classList.remove('is-delivered');

        if (status === 'in_transit') {
            liveBanner.hidden = false;
            liveTitle.textContent = 'Сейчас курьер едет к вам!';
            liveText.textContent = 'Курьер уже забрал ваш заказ и направляется по указанному адресу.';
            liveIcon.textContent = '➤';
        } else if (status === 'delivered') {
            liveBanner.hidden = false;
            liveBanner.classList.add('is-delivered');
            liveTitle.textContent = 'Заказ доставлен';
            liveText.textContent = 'Приятного аппетита! Заказ передан вам.';
            liveIcon.textContent = '✓';
        } else if (status === 'handed_to_courier') {
            liveBanner.hidden = false;
            liveTitle.textContent = 'Заказ передан курьеру';
            liveText.textContent = 'Курьер получил заказ. Скоро он отправится к вам.';
            liveIcon.textContent = '⌁';
        }
    }

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

            const nextStatus = String(data.status ?? '');
            const nextPaymentStatus = String(data.payment_status ?? '');

            // Первый ответ сверяем с серверным состоянием при открытии страницы,
            // звук проигрываем только при последующем реальном изменении.
            if (
                (lastKnownStatus && nextStatus && nextStatus !== lastKnownStatus) ||
                (lastKnownPaymentStatus && nextPaymentStatus && nextPaymentStatus !== lastKnownPaymentStatus)
            ) {
                playCustomerOrderSound();
            }

            if (nextStatus) lastKnownStatus = nextStatus;
            if (nextPaymentStatus) lastKnownPaymentStatus = nextPaymentStatus;

            statusElement.textContent = 'Статус: ' + (statusLabels[data.status] || data.status);
            renderDeliveryStatus(data.status);
            if (pickupAction) {
                pickupAction.hidden = data.status !== 'ready';
            }
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
