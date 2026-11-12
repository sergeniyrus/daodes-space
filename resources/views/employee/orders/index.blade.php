@extends('template')

@section('title_page')
    Рабочие заказы — {{ $organization->name }}
@endsection

@section('main')
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
@media(max-width:600px) {
    .employee-orders-panel { padding: 15px; }
}
</style>

<div class="employee-orders-page">
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

    <div class="employee-orders-panel">
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
                    <div class="employee-orders-status">{{ $order->status }}</div>
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
                    Получение: {{ $order->fulfillment_type }}
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
    </div>
</div>
@endsection
