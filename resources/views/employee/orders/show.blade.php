@extends('template')

@section('title_page')
    Доставка заказа №{{ $order->order_number }}
@endsection

@section('main')
@php
    $deliveryAddress = $order->delivery_address ?? [];
    $addressLine = $deliveryAddress['address_line'] ?? '';
    $statusLabel = match ($order->status) {
        'ready' => 'Готов, ожидает передачи курьеру',
        'handed_to_courier' => 'Передан курьеру',
        default => 'В пути',
    };
@endphp

<style>
.courier-order-detail {
    width: min(820px, calc(100% - 24px));
    margin: 25px auto 50px;
    color: #f4f4f4;
}
.courier-order-detail-panel {
    padding: 21px;
    margin-bottom: 15px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .42);
    border-radius: 17px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, .3);
}
.courier-order-detail h1 { margin: 0 0 8px; color: #ffd700; }
.courier-order-detail h2 { margin: 0 0 11px; color: #00e5d4; font-size: 1rem; }
.courier-order-detail-muted { color: #aeb5c4; }
.courier-order-detail-address { color: #fff; font-size: 1.15rem; font-weight: 800; overflow-wrap: anywhere; }
.courier-order-detail-line { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid rgba(0,229,212,.14); }
.courier-order-detail-items { display:grid; gap:8px; }
.courier-order-detail-item { display:flex; justify-content:space-between; gap:12px; }
.courier-order-detail-qty { color:#ffd700; font-weight:800; white-space:nowrap; }
.courier-order-detail-actions { display:grid; gap:10px; margin-top:18px; }
.courier-order-detail-button {
    display:flex; align-items:center; justify-content:center; width:100%; min-height:52px;
    padding:10px 16px; color:#00e5d4; background:rgba(0,229,212,.07);
    border:1px solid rgba(0,229,212,.65); border-radius:12px; font-weight:800;
    text-align:center; text-decoration:none; cursor:pointer;
}
.courier-order-detail-button:hover { color:#081016; background:#00e5d4; }
@media(max-width:600px) {
    .courier-order-detail { width:calc(100% - 16px); margin:12px auto 30px; }
    .courier-order-detail-panel { padding:15px; }
    .courier-order-detail-line { display:block; }
}
</style>

<div class="courier-order-detail">
    @if(session('success'))
        <div class="courier-order-detail-panel" style="color:#00e5a8;">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="courier-order-detail-panel" style="color:#ff9292;">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="courier-order-detail-panel">
        <a href="{{ route('employee.organization.orders.index', $organization) }}" style="color:#00e5d4;text-decoration:none;">← К моим доставкам</a>
        <h1 style="margin-top:14px;">Заказ №{{ $order->order_number }}</h1>
        <div class="courier-order-detail-muted">{{ $organization->name }} · {{ $employee->name }}</div>
        <p style="color:#00e5a8;font-weight:800;">{{ $statusLabel }}</p>
        <div class="courier-order-detail-muted">
            {{ $order->status === 'ready' ? 'Заказ готов и назначен вам. Ожидайте, пока менеджер или повар передаст его курьеру.' : ($order->status === 'handed_to_courier' ? 'Заказ ожидает начала доставки.' : 'Вы уже направляетесь к клиенту.') }}
            @if($order->updated_at)
                <div>Обновлён: {{ $order->updated_at->format('d.m.Y H:i') }}</div>
            @endif
        </div>
        @if($order->requested_at)
            <div class="courier-order-detail-line">
                <span class="courier-order-detail-muted">Доставить к</span>
                <strong>{{ $order->requested_at->format('d.m.Y H:i') }}</strong>
            </div>
        @endif
    </section>

    <section class="courier-order-detail-panel">
        <h2>📍 Адрес доставки</h2>
        <div class="courier-order-detail-address">{{ $addressLine ?: 'Адрес не указан' }}</div>
        @if(!empty($deliveryAddress['entrance']) || !empty($deliveryAddress['floor']) || !empty($deliveryAddress['apartment']) || !empty($deliveryAddress['intercom']))
            <div class="courier-order-detail-line">
                <span class="courier-order-detail-muted">Подробности</span>
                <strong>
                    @if(!empty($deliveryAddress['entrance'])) Подъезд {{ $deliveryAddress['entrance'] }} @endif
                    @if(!empty($deliveryAddress['floor'])) · этаж {{ $deliveryAddress['floor'] }} @endif
                    @if(!empty($deliveryAddress['apartment'])) · кв./офис {{ $deliveryAddress['apartment'] }} @endif
                    @if(!empty($deliveryAddress['intercom'])) · домофон {{ $deliveryAddress['intercom'] }} @endif
                </strong>
            </div>
        @endif
        @if($addressLine)
            <div class="courier-order-detail-actions">
                <a class="courier-order-detail-button" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($addressLine) }}">Открыть маршрут ↗</a>
            </div>
        @endif
    </section>

    <section class="courier-order-detail-panel">
        <h2>👤 Получатель</h2>
        <div class="courier-order-detail-address">{{ $order->customer_name }}</div>
        @if($order->customer_phone)
            <div class="courier-order-detail-actions">
                <a class="courier-order-detail-button" href="tel:{{ preg_replace('/[^0-9+]/', '', $order->customer_phone) }}">Позвонить {{ $order->customer_phone }}</a>
            </div>
        @endif
    </section>

    @if(!empty($deliveryAddress['delivery_comment']) || $order->customer_comment)
        <section class="courier-order-detail-panel">
            <h2>💬 Комментарии к доставке</h2>
            @if(!empty($deliveryAddress['delivery_comment']))
                <p>{{ $deliveryAddress['delivery_comment'] }}</p>
            @endif
            @if($order->customer_comment)
                <p class="courier-order-detail-muted">К заказу: {{ $order->customer_comment }}</p>
            @endif
        </section>
    @endif

    <section class="courier-order-detail-panel">
        <h2>Состав заказа</h2>
        <div class="courier-order-detail-items">
            @foreach($order->items as $item)
                <div class="courier-order-detail-item">
                    <span>{{ $item->item_name }}@if($item->weight || $item->unit) · {{ $item->weight }} {{ $item->unit }}@endif</span>
                    <span class="courier-order-detail-qty">× {{ $item->quantity }}</span>
                </div>
            @endforeach
        </div>
        <div class="courier-order-detail-line">
            <span class="courier-order-detail-muted">Способ оплаты</span>
            <strong>{{ $order->payment_method === 'cash_on_receipt' ? 'Наличные при получении' : ($order->payment_method === 'card_on_receipt' ? 'Картой при получении' : 'Онлайн') }}</strong>
        </div>
        <div class="courier-order-detail-line">
            <span class="courier-order-detail-muted">Состояние оплаты</span>
            <strong>{{ $order->payment_status === 'succeeded' ? 'Оплачено' : ($order->payment_status === 'pending' ? 'Ожидает оплаты' : ($order->payment_status === 'failed' ? 'Ошибка оплаты' : $order->payment_status)) }}</strong>
        </div>
        @if($order->payment_status === 'pending' && in_array($order->payment_method, ['cash_on_receipt', 'card_on_receipt'], true))
            <p class="courier-order-detail-muted">Получите оплату при вручении. После доставки менеджер отметит оплату в системе.</p>
        @elseif($order->payment_method === 'online' && $order->payment_status !== 'succeeded')
            <p class="courier-order-detail-muted">Онлайн-оплата ещё не подтверждена системой. Уточните ситуацию у менеджера.</p>
        @endif
        <div class="courier-order-detail-line">
            <span class="courier-order-detail-muted">Сумма заказа</span>
            <strong style="color:#ffd700;">{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</strong>
        </div>

        <div class="courier-order-detail-actions">
            @if($order->status === 'handed_to_courier')
                <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="in_transit">
                    <button class="courier-order-detail-button" type="submit">Принять заказ и начать доставку</button>
                </form>
            @elseif($order->status === 'in_transit')
                <form method="POST" action="{{ route('employee.organization.orders.status', [$organization, $order]) }}" onsubmit="return confirm('Подтвердить, что заказ фактически передан клиенту?');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="delivered">
                    <button class="courier-order-detail-button" type="submit">Подтвердить вручение заказа</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endsection
