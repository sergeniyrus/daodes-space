@extends('customer.layouts.workspace')

@section('title_page')
    Мои заказы
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
@endphp
<style>
.customer-orders-page {
    width: min(900px, calc(100% - 24px));
    margin: 28px auto 55px;
    color: #f4f4f4;
}
.customer-orders-panel {
    padding: 22px;
    margin-bottom: 16px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .45);
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .35);
}
.customer-orders-title { margin: 0 0 18px; color: #ffd700; }
.customer-orders-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 15px 0;
    border-bottom: 1px solid rgba(0, 229, 212, .2);
}
.customer-orders-number { color: #ffd700; font-weight: 700; }
.customer-orders-muted { color: #aeb5c4; font-size: .9rem; }
.customer-orders-status { color: #00e5a8; font-weight: 700; }
.customer-orders-total { color: #ffd700; font-weight: 700; white-space: nowrap; }
.customer-orders-link { color: #00e5d4; text-decoration: none; }
.customer-orders-empty { color: #aeb5c4; }
@media(max-width:600px) {
    .customer-orders-panel { padding: 16px; }
    .customer-orders-card { align-items: flex-start; flex-direction: column; }
}
</style>

<div class="customer-orders-page">
    <div class="customer-orders-panel">
        <h1 class="customer-orders-title">Мои заказы</h1>
        <p style="margin:-8px 0 16px"><a class="customer-orders-link" href="{{ route('customer.feedback.index') }}">☆ Книга отзывов и предложений / Мои оценки →</a></p>

        @forelse($orders as $order)
            <div class="customer-orders-card">
                <div>
                    <div class="customer-orders-number">
                        №{{ $order->order_number }} · {{ $order->organization?->name }}
                    </div>
                    <div class="customer-orders-muted">
                        {{ $order->created_at?->timezone($order->organization?->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') }}
                    </div>
                    <div class="customer-orders-status">
                        Статус: {{ $statusLabels[$order->status] ?? $order->status }}
                    </div>
                </div>

                <div>
                    <div class="customer-orders-total">
                        {{ number_format((float) $order->total, 2, ',', ' ') }}
                        {{ $order->currency }}
                    </div>
                    <a
                        class="customer-orders-link"
                        href="{{ route('customer.orders.show', $order) }}"
                    >
                        Подробнее →
                    </a>
                </div>
            </div>
        @empty
            <p class="customer-orders-empty">У вас пока нет оформленных заказов.</p>
        @endforelse

        {{ $orders->links() }}

        <div style="margin-top: 18px;">
            <a class="customer-orders-link" href="{{ route('customer.cart') }}">
                Перейти в корзину
            </a>
        </div>
    </div>
</div>
@endsection
