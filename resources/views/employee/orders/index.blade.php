@extends('template')

@section('title_page')
    Рабочие заказы — {{ $organization->name }}
@endsection

@section('main')
@php
    $statusLabels = [
        'new' => 'Новый',
        'confirmed' => 'Подтверждён',
        'preparing' => 'Готовится',
        'ready' => 'Готов',
        'handed_to_courier' => 'Передан курьеру',
        'in_transit' => 'В пути',
        'delivered' => 'Доставлен',
        'issued' => 'Выдан',
        'served' => 'Подан',
        'completed' => 'Завершён',
        'cancelled' => 'Отменён',
    ];
@endphp
<style>
.employee-orders-page {
    width: min(1050px, calc(100% - 24px));
    margin: 28px auto 55px;
    color: #f4f4f4;
}
.employee-orders-panel {
    padding: 20px;
    margin-bottom: 16px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .45);
    border-radius: 17px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .32);
}
.employee-orders-title { margin: 0 0 8px; color: #ffd700; }
.employee-orders-muted { color: #aeb5c4; font-size: .9rem; }
.employee-orders-card {
    padding: 17px 0;
    border-top: 1px solid rgba(0, 229, 212, .2);
}
.employee-orders-head {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.employee-orders-number { color: #ffd700; font-size: 1.1rem; font-weight: 700; }
.employee-orders-status { color: #00e5a8; font-weight: 700; }
.employee-orders-button {
    min-height: 40px;
    padding: 8px 15px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .06);
    border: 1px solid rgba(0, 229, 212, .65);
    border-radius: 20px;
    cursor: pointer;
    font-weight: 700;
}
.employee-orders-button:hover { color: #081016; background: #00e5d4; }
.employee-orders-error { color: #ff9292; }
.employee-orders-lines { margin: 12px 0; }
.kds-board { display:grid; grid-template-columns:repeat(3,minmax(260px,1fr)); gap:14px; align-items:start; }
.kds-lane { min-width:0; padding:14px; background:rgba(4,8,18,.72); border:1px solid rgba(0,229,212,.24); border-radius:14px; }
.kds-lane-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0 0 12px; padding-bottom:10px; border-bottom:1px solid rgba(255,215,0,.28); }
.kds-lane-title { margin:0; color:#ffd700; font-size:1.05rem; }
.kds-lane-count { min-width:28px; padding:3px 8px; color:#071116; background:#00e5d4; border-radius:20px; text-align:center; font-weight:800; }
.kds-order { padding:13px; margin-bottom:12px; background:linear-gradient(145deg,rgba(21,27,44,.98),rgba(10,14,26,.98)); border:1px solid rgba(255,215,0,.28); border-radius:12px; }
.kds-order:last-child { margin-bottom:0; }
.kds-order-top { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
.kds-order-number { color:#ffd700; font-size:1.15rem; font-weight:800; }
.kds-order-age { flex:0 0 auto; padding:4px 7px; color:#00e5d4; background:rgba(0,229,212,.09); border-radius:7px; font-size:.78rem; font-weight:700; }
.kds-order-created { margin-top:3px; color:#9ca8bb; font-size:.78rem; }
.kds-order-items { margin:12px 0; padding:10px 0; border-top:1px solid rgba(0,229,212,.16); border-bottom:1px solid rgba(0,229,212,.16); }
.kds-order-item { display:flex; justify-content:space-between; gap:10px; padding:4px 0; }
.kds-order-item-name { color:#f4f4f4; font-weight:600; }
.kds-order-item-quantity { flex:0 0 auto; color:#ffd700; font-weight:800; }
.kds-order-meta { color:#aeb5c4; font-size:.84rem; line-height:1.55; }
.kds-order-action { margin-top:12px; } .kds-order-action form { margin:0; }
.kds-empty { padding:16px 8px; color:#7f899a; text-align:center; font-size:.85rem; }
.kds-pagination { margin-top:18px; }
@media(max-width:900px) { .kds-board { grid-template-columns:1fr; } }
.courier-board { display:grid; grid-template-columns:minmax(0,1fr); gap:14px; }
.courier-card { padding:18px; background:linear-gradient(145deg,#151b2c,#0a0e1a); border:1px solid rgba(0,229,212,.34); border-radius:15px; box-shadow:0 8px 24px rgba(0,0,0,.22); }
.courier-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
.courier-order-number { color:#ffd700; font-size:1.3rem; font-weight:800; }
.courier-status { display:inline-flex; align-items:center; min-height:30px; padding:4px 10px; color:#00e5d4; background:rgba(0,229,212,.1); border:1px solid rgba(0,229,212,.3); border-radius:20px; font-size:.82rem; font-weight:800; }
.courier-wait { margin-top:5px; color:#aeb5c4; font-size:.82rem; }
.courier-section { margin-top:15px; padding-top:13px; border-top:1px solid rgba(0,229,212,.17); }
.courier-section-title { margin:0 0 9px; color:#ffd700; font-size:.92rem; font-weight:800; }
.courier-detail { display:flex; justify-content:space-between; gap:12px; padding:5px 0; color:#e7eaf1; }
.courier-detail-label { color:#9ca8bb; }
.courier-items { display:grid; gap:7px; }
.courier-item { display:flex; justify-content:space-between; gap:12px; color:#f4f4f4; }
.courier-item-qty { flex:0 0 auto; color:#ffd700; font-weight:800; }
.courier-address { color:#fff; font-size:1.05rem; font-weight:700; line-height:1.5; overflow-wrap:anywhere; }
.courier-contact { display:flex; flex-wrap:wrap; gap:9px; margin-top:10px; }
.courier-contact-link { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:8px 14px; color:#00e5d4; text-decoration:none; border:1px solid rgba(0,229,212,.55); border-radius:22px; font-weight:700; }
.courier-action { margin-top:17px; } .courier-action form { margin:0; }
.courier-action .employee-orders-button { width:100%; min-height:54px; border-radius:12px; font-size:1rem; }
.courier-empty { padding:28px 14px; color:#aeb5c4; text-align:center; }
@media(max-width:600px) { .employee-orders-page { width:calc(100% - 16px); margin:12px auto 30px; } .employee-orders-panel { padding:13px; border-radius:13px; } .courier-card { padding:14px; } .courier-detail { display:block; } .courier-detail-label { display:block; margin-bottom:2px; font-size:.78rem; } .courier-contact-link { flex:1 1 130px; } }
@media(max-width:600px) {
    .employee-orders-panel { padding: 15px; }
}
</style>

<div
    class="employee-orders-page"
    data-orders-updates-url="{{ route('employee.organization.orders.updates', $organization) }}"
>
    @if(session('success'))
        <div class="employee-orders-panel employee-orders-status">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="employee-orders-panel employee-orders-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="employee-orders-panel">
        <h1 class="employee-orders-title">{{ $organization->name }}</h1>
        <div class="employee-orders-muted">
            {{ $employee->name }} ·
            {{ $membership->role === 'cook' ? 'Повар' : 'Курьер' }}
        </div>
        <div style="margin-top: 14px;">
            <a href="{{ route('employee.dashboard') }}" style="color: #00e5d4; text-decoration: none;">
                ← Рабочий кабинет
            </a>
        </div>
    </div>

    @include('components.order-notifications', [
        'notificationsUrl' => route('employee.organization.orders.notifications', $organization),
        'notificationReadUrlTemplate' => route('employee.organization.orders.notifications.read', [$organization, '__ID__']),
    ])

    <div class="employee-orders-panel" id="employee-orders-list">
        @if($membership->role === 'cook')
            @php
                $kdsLanes = [
                    'new' => 'Новые — принять',
                    'confirmed' => 'К приготовлению',
                    'preparing' => 'Готовятся',
                    'ready' => 'Готовы',
                ];
            @endphp
            <div class="kds-board">
                @foreach($kdsLanes as $laneStatus => $laneTitle)
                    @php
                        $laneOrders = $orders->getCollection()->where('status', $laneStatus);
                    @endphp
                    <section class="kds-lane" aria-label="{{ $laneTitle }}">
                        <div class="kds-lane-header">
                            <h2 class="kds-lane-title">{{ $laneTitle }}</h2>
                            <span class="kds-lane-count">{{ $laneOrders->count() }}</span>
                        </div>
                        @forelse($laneOrders as $order)
                            <article class="kds-order">
                                <div class="kds-order-top">
                                    <div>
                                        <div class="kds-order-number">Заказ №{{ $order->order_number }}</div>
                                        <div class="kds-order-created">{{ $order->created_at?->format('d.m.Y H:i') }}</div>
                                    </div>
                                    <time class="kds-order-age" data-kds-created-at="{{ $order->created_at?->toIso8601String() }}">—</time>
                                </div>
                                <div class="kds-order-items">
                                    @foreach($order->items as $item)
                                        <div class="kds-order-item">
                                            <span class="kds-order-item-name">
                                                {{ $item->item_name }}
                                                @if($item->weight || $item->unit)
                                                    <span class="employee-orders-muted">· {{ $item->weight }} {{ $item->unit }}</span>
                                                @endif
                                            </span>
                                            <span class="kds-order-item-quantity">× {{ $item->quantity }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="kds-order-meta">
                                    @switch($order->fulfillment_type)
                                        @case('delivery') Доставка @break
                                        @case('pickup') Самовывоз @break
                                        @case('dine_in') В заведении @break
                                        @default {{ $order->fulfillment_type }}
                                    @endswitch
                                    @if($order->requested_at)
                                        <div>Ко времени: {{ $order->requested_at->format('d.m.Y H:i') }}</div>
                                    @endif
                                </div>
                                <div class="kds-order-action">
                                    @if(in_array($order->status, ['new', 'confirmed'], true))
                                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="preparing">
                                            <button class="employee-orders-button" type="submit">{{ $order->status === 'new' ? 'Принять и начать готовить' : 'Начать готовить' }}</button>
                                        </form>
                                    @elseif($order->status === 'preparing')
                                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="ready">
                                            <button class="employee-orders-button" type="submit">Заказ готов</button>
                                        </form>
                                    @elseif($order->status === 'ready' && $order->fulfillment_type === 'delivery')
                                        <div class="employee-orders-muted">Готов. Ожидает, когда курьер самостоятельно заберёт заказ.</div>
                                    @elseif($order->status === 'ready' && $order->fulfillment_type === 'pickup')
                                        <div class="employee-orders-muted">Готов. Клиент может забрать заказ самостоятельно.</div>
                                    @elseif($order->status === 'ready')
                                        <div class="employee-orders-muted">Заказ готов к подаче.</div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="kds-empty">Заказов в этой зоне нет</div>
                        @endforelse
                    </section>
                @endforeach
            </div>
            <div class="kds-pagination">{{ $orders->links() }}</div>
        @else
        <div class="courier-board">
            @forelse($orders as $order)
                @php
                    $deliveryAddress = $order->delivery_address ?? [];
                    $addressLine = $deliveryAddress['address_line'] ?? '';
                    $courierStatusLabel = $statusLabels[$order->status] ?? $order->status;
                @endphp
                <article class="courier-card">
                    <div class="courier-card-head">
                        <div>
                            <div class="courier-order-number">Заказ №{{ $order->order_number }}</div>
                            <div
                                class="courier-wait"
                                data-courier-created-at="{{ $order->updated_at?->toIso8601String() }}"
                                data-courier-created-label="{{ match ($order->status) {
                                    'ready' => 'Готов, ожидает передачи курьеру',
                                    'handed_to_courier' => 'Передан курьеру',
                                    'in_transit' => 'В пути с',
                                    'delivered' => 'Доставлен, ожидает закрытия',
                                    default => 'Обновлён',
                                } }} · {{ $order->updated_at?->format('d.m.Y H:i') }}"
                            >
                                {{ match ($order->status) {
                                    'ready' => 'Готов, ожидает передачи курьеру',
                                    'handed_to_courier' => 'Передан курьеру',
                                    'in_transit' => 'В пути с',
                                    'delivered' => 'Доставлен, ожидает закрытия',
                                    default => 'Обновлён',
                                } }}
                                · {{ $order->updated_at?->format('d.m.Y H:i') }}
                            </div>
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                            <span class="courier-status">{{ $courierStatusLabel }}</span>
                            <a
                                class="courier-contact-link"
                                href="{{ route('employee.organization.orders.show', [$organization, $order]) }}"
                            >Открыть заказ</a>
                        </div>
                    </div>

                    <div class="courier-section">
                        <h2 class="courier-section-title">📍 Адрес доставки</h2>
                        <div class="courier-address">{{ $addressLine ?: 'Адрес не указан' }}</div>
                        @if($order->requested_at)
                            <div class="courier-detail" style="margin-top: 8px;">
                                <span class="courier-detail-label">Доставка к</span>
                                <strong>{{ $order->requested_at->format('d.m.Y H:i') }}</strong>
                            </div>
                        @endif
                        @if(!empty($deliveryAddress['entrance']) || !empty($deliveryAddress['floor']) || !empty($deliveryAddress['apartment']) || !empty($deliveryAddress['intercom']))
                            <div class="courier-detail">
                                <span class="courier-detail-label">Детали адреса</span>
                                <span>
                                    @if(!empty($deliveryAddress['entrance'])) Подъезд {{ $deliveryAddress['entrance'] }} @endif
                                    @if(!empty($deliveryAddress['floor'])) · этаж {{ $deliveryAddress['floor'] }} @endif
                                    @if(!empty($deliveryAddress['apartment'])) · кв./офис {{ $deliveryAddress['apartment'] }} @endif
                                    @if(!empty($deliveryAddress['intercom'])) · домофон {{ $deliveryAddress['intercom'] }} @endif
                                </span>
                            </div>
                        @endif
                        @if($addressLine)
                            <div class="courier-contact">
                                <a class="courier-contact-link" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($addressLine) }}">Открыть маршрут ↗</a>
                            </div>
                        @endif
                    </div>

                    <div class="courier-section">
                        <h2 class="courier-section-title">👤 Получатель</h2>
                        <div class="courier-address">{{ $order->customer_name }}</div>
                        <div class="courier-contact">
                            @if($order->customer_phone)
                                <a class="courier-contact-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $order->customer_phone) }}">Позвонить {{ $order->customer_phone }}</a>
                            @endif
                        </div>
                    </div>

                    @if(!empty($deliveryAddress['delivery_comment']) || $order->customer_comment)
                        <div class="courier-section">
                            <h2 class="courier-section-title">💬 Комментарии</h2>
                            @if(!empty($deliveryAddress['delivery_comment']))
                                <div>{{ $deliveryAddress['delivery_comment'] }}</div>
                            @endif
                            @if($order->customer_comment)
                                <div class="employee-orders-muted" style="margin-top:7px;">К заказу: {{ $order->customer_comment }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="courier-section">
                        <h2 class="courier-section-title">Состав заказа</h2>
                        <div class="courier-items">
                            @foreach($order->items as $item)
                                <div class="courier-item">
                                    <span>{{ $item->item_name }}@if($item->weight || $item->unit) · {{ $item->weight }} {{ $item->unit }}@endif</span>
                                    <span class="courier-item-qty">× {{ $item->quantity }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="courier-detail" style="margin-top:10px;">
                            <span class="courier-detail-label">Способ оплаты</span>
                            <strong>{{ $order->payment_method === 'cash_on_receipt' ? 'Наличные при получении' : ($order->payment_method === 'card_on_receipt' ? 'Картой при получении' : 'Онлайн') }}</strong>
                        </div>
                        <div class="courier-detail">
                            <span class="courier-detail-label">Статус оплаты</span>
                            <strong>{{ $order->payment_status === 'succeeded' ? 'Оплачено' : ($order->payment_status === 'pending' ? 'Ожидает оплаты' : ($order->payment_status === 'failed' ? 'Ошибка оплаты' : $order->payment_status)) }}</strong>
                        </div>
                        @if($order->payment_status === 'pending' && in_array($order->payment_method, ['cash_on_receipt', 'card_on_receipt'], true))
                            <div class="employee-orders-muted" style="margin-top:8px;">
                                Получите оплату при вручении заказа и подтвердите её в карточке доставки.
                            </div>
                        @elseif($order->payment_method === 'online' && $order->payment_status !== 'succeeded')
                            <div class="employee-orders-muted" style="margin-top:8px;">
                                Онлайн-оплата ещё не подтверждена системой. При необходимости уточните ситуацию у менеджера.
                            </div>
                        @endif
                        <div class="courier-detail">
                            <span class="courier-detail-label">Сумма</span>
                            <strong>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</strong>
                        </div>
                    </div>

                    <div class="courier-action">
                        @if($order->status === 'ready')
                            <form method="POST" action="{{ route('employee.organization.orders.claim', [$organization, $order]) }}" onsubmit="return confirm('Забрать заказ №{{ $order->order_number }} в доставку?');">
                                @csrf
                                <button class="employee-orders-button" type="submit">Забрать заказ в доставку</button>
                            </form>
                        @elseif($order->status === 'handed_to_courier')
                            <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="in_transit">
                                <button class="employee-orders-button" type="submit">Принять заказ и начать доставку</button>
                            </form>
                        @elseif($order->status === 'in_transit')
                            <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}" onsubmit="return confirm('Подтвердить, что заказ фактически передан клиенту?');">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="delivered">
                                <button class="employee-orders-button" type="submit">Подтвердить вручение заказа</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="courier-empty">Свободных готовых доставок пока нет. Новые заказы появятся здесь автоматически.</div>
            @endforelse
        </div>

        {{ $orders->links() }}
        @endif
    </div>
</div>

<script>
(() => {
    const page = document.querySelector('.employee-orders-page[data-orders-updates-url]');
    let list = document.getElementById('employee-orders-list');

    if (!page || !list) {
        return;
    }

    const updateKdsTimers = () => {
        document.querySelectorAll('[data-kds-created-at]').forEach((element) => {
            const createdAt = Date.parse(element.dataset.kdsCreatedAt);
            if (Number.isNaN(createdAt)) { element.textContent = 'Время неизвестно'; return; }
            const elapsedMinutes = Math.max(0, Math.floor((Date.now() - createdAt) / 60000));
            const hours = Math.floor(elapsedMinutes / 60);
            const minutes = elapsedMinutes % 60;
            element.textContent = hours > 0 ? ('Ожидание ' + hours + ' ч ' + minutes + ' мин') : ('Ожидание ' + minutes + ' мин');
        });
        document.querySelectorAll('[data-courier-created-at]').forEach((element) => {
            const createdAt = Date.parse(element.dataset.courierCreatedAt);
            if (Number.isNaN(createdAt)) {
                element.textContent = 'Время неизвестно';
                return;
            }
            const elapsedMinutes = Math.max(0, Math.floor((Date.now() - createdAt) / 60000));
            const hours = Math.floor(elapsedMinutes / 60);
            const minutes = elapsedMinutes % 60;
            const createdLabel = element.dataset.courierCreatedLabel || element.textContent;
            element.dataset.courierCreatedLabel = createdLabel;
            element.textContent = createdLabel + ' · прошло '
                + (hours > 0 ? (hours + ' ч ' + minutes + ' мин') : (minutes + ' мин'));
        });
    };
    updateKdsTimers();
    window.setInterval(updateKdsTimers, 60000);
    let requestInProgress = false;

    const refreshOrders = async () => {
        if (requestInProgress || document.hidden) {
            return;
        }

        requestInProgress = true;

        try {
            const response = await fetch(page.dataset.ordersUpdatesUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error('Не удалось обновить рабочую очередь.');
            }

            const html = await response.text();
            const documentFromServer = new DOMParser().parseFromString(html, 'text/html');
            const updatedList = documentFromServer.getElementById('employee-orders-list');

            if (!updatedList) {
                throw new Error('Сервер вернул некорректный ответ.');
            }

            if (list.innerHTML !== updatedList.innerHTML) {
                const replacement = document.importNode(updatedList, true);
                list.replaceWith(replacement);
                list = replacement;
            }
        } catch (error) {
            console.warn('Employee orders polling:', error);
        } finally {
            requestInProgress = false;
        }
    };

    window.setInterval(refreshOrders, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshOrders();
        }
    });
})();
</script>
@endsection
