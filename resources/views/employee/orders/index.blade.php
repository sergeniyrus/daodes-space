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
                                    @if($order->status === 'confirmed')
                                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="preparing">
                                            <button class="employee-orders-button" type="submit">Начать готовить</button>
                                        </form>
                                    @elseif($order->status === 'preparing')
                                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="ready">
                                            <button class="employee-orders-button" type="submit">Заказ готов</button>
                                        </form>
                                    @elseif($order->status === 'ready' && $order->fulfillment_type === 'delivery' && $order->assignments->where('role', 'courier')->whereNull('unassigned_at')->isNotEmpty())
                                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="handed_to_courier">
                                            <button class="employee-orders-button" type="submit">Передать курьеру</button>
                                        </form>
                                    @elseif($order->status === 'ready')
                                        <div class="employee-orders-muted">Ожидает выдачи или передачи</div>
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
        @forelse($orders as $order)
            <article class="employee-orders-card">
                <div class="employee-orders-head">
                    <div>
                        <div class="employee-orders-number">
                            Заказ №{{ $order->order_number }}
                        </div>
                        <div class="employee-orders-muted">
                            {{ $order->created_at?->format('d.m.Y H:i') }}
                        </div>
                    </div>
                    <div class="employee-orders-status">{{ $statusLabels[$order->status] ?? $order->status }}</div>
                </div>

                <div class="employee-orders-lines">
                    @foreach($order->items as $item)
                        <div>
                            {{ $item->item_name }} × {{ $item->quantity }}
                            @if($item->weight || $item->unit)
                                · {{ $item->weight }} {{ $item->unit }}
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="employee-orders-muted">
                    Получение:
                    @switch($order->fulfillment_type)
                        @case('delivery') Доставка @break
                        @case('pickup') Самовывоз @break
                        @case('dine_in') В заведении @break
                        @default {{ $order->fulfillment_type }}
                    @endswitch
                    @if($order->requested_at)
                        · Время: {{ $order->requested_at->format('d.m.Y H:i') }}
                    @endif
                </div>

                @if($order->fulfillment_type === 'delivery' && $membership->role === 'courier')
                    <div class="employee-orders-muted" style="margin-top: 8px;">
                        Адрес: {{ $order->delivery_address['address_line'] ?? '' }}
                        @if(!empty($order->delivery_address['entrance']))
                            · подъезд {{ $order->delivery_address['entrance'] }}
                        @endif
                        @if(!empty($order->delivery_address['apartment']))
                            · кв./офис {{ $order->delivery_address['apartment'] }}
                        @endif
                    </div>
                    <div class="employee-orders-muted">
                        Получатель: {{ $order->customer_name }} · {{ $order->customer_phone }}
                    </div>
                    @if(!empty($order->delivery_address['delivery_comment']))
                        <div class="employee-orders-muted">
                            Комментарий: {{ $order->delivery_address['delivery_comment'] }}
                        </div>
                    @endif
                @endif

                <div style="margin-top: 13px;">
                    @if($membership->role === 'cook' && $order->status === 'confirmed')
                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="preparing">
                            <button class="employee-orders-button" type="submit">Начать готовить</button>
                        </form>
                    @elseif($membership->role === 'cook' && $order->status === 'preparing')
                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="ready">
                            <button class="employee-orders-button" type="submit">Заказ готов</button>
                        </form>
                    @elseif($membership->role === 'cook' && $order->status === 'ready' && $order->fulfillment_type === 'delivery' && $order->assignments->where('role', 'courier')->whereNull('unassigned_at')->isNotEmpty())
                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="handed_to_courier">
                            <button class="employee-orders-button" type="submit">Передать курьеру</button>
                        </form>
                    @elseif($membership->role === 'courier' && $order->status === 'handed_to_courier')
                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="in_transit">
                            <button class="employee-orders-button" type="submit">Принять заказ и начать доставку</button>
                        </form>
                    @elseif($membership->role === 'courier' && $order->status === 'in_transit')
                        <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="delivered">
                            <button class="employee-orders-button" type="submit">Отметить доставленным</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="employee-orders-muted">
                Заказов, назначенных вам, пока нет.
            </div>
        @endforelse

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
