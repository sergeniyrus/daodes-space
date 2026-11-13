@extends('template')

@section('title_page')
    Заказы — {{ $organization->name }}
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
.organization-orders-page {
    width: min(1180px, calc(100% - 24px));
    margin: 28px auto 55px;
    color: #f4f4f4;
}
.organization-orders-panel {
    padding: 20px;
    margin-bottom: 16px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .45);
    border-radius: 17px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .32);
}
.organization-orders-title { margin: 0 0 8px; color: #ffd700; }
.organization-orders-muted { color: #aeb5c4; font-size: .9rem; }
.organization-orders-card {
    padding: 17px 0;
    border-top: 1px solid rgba(0, 229, 212, .2);
}
.organization-orders-head {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.organization-orders-number { color: #ffd700; font-size: 1.1rem; font-weight: 700; }
.organization-orders-status { color: #00e5a8; font-weight: 700; }
.organization-orders-lines { margin: 12px 0; color: #e4e7ee; }
.organization-orders-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-top: 13px;
}
.organization-orders-actions form {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.organization-orders-select {
    min-height: 39px;
    padding: 7px 10px;
    color: #fff;
    background: #090c16;
    border: 1px solid rgba(0, 229, 212, .4);
    border-radius: 8px;
}
.organization-orders-button {
    min-height: 39px;
    padding: 7px 14px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .06);
    border: 1px solid rgba(0, 229, 212, .65);
    border-radius: 20px;
    cursor: pointer;
    font-weight: 700;
}
.organization-orders-button:hover { color: #081016; background: #00e5d4; }
.organization-orders-danger {
    color: #ff9292;
    border-color: rgba(255, 100, 100, .65);
}
.organization-orders-warning {
    padding: 10px 12px;
    color: #ffd78b;
    background: rgba(255, 180, 60, .06);
    border: 1px solid rgba(255, 180, 60, .3);
    border-radius: 9px;
}
.organization-orders-assignment {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
    color: #aeb5c4;
    font-size: .88rem;
}
.organization-orders-error { color: #ff9292; }
.organization-orders-total { color: #ffd700; font-weight: 700; }
@media(max-width:600px) {
    .organization-orders-panel { padding: 15px; }
    .organization-orders-actions,
    .organization-orders-actions form { flex-direction: column; align-items: stretch; }
}
</style>

<div
    class="organization-orders-page"
    data-orders-updates-url="{{ route('organizations.orders.updates', $organization) }}"
>
    @if(session('success'))
        <div class="organization-orders-panel organization-orders-status">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="organization-orders-panel organization-orders-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="organization-orders-panel">
        <h1 class="organization-orders-title">Заказы организации</h1>
        <div class="organization-orders-muted">{{ $organization->name }}</div>
        <div style="margin-top: 14px;">
            <a href="{{ route('organizations.manage', $organization) }}" style="color: #00e5d4; text-decoration: none;">
                ← Панель организации
            </a>
        </div>
    </div>

    <div class="organization-orders-panel" id="organization-orders-list">
        @forelse($orders as $order)
            <article class="organization-orders-card">
                <div class="organization-orders-head">
                    <div>
                        <div class="organization-orders-number">
                            Заказ №{{ $order->order_number }}
                        </div>
                        <div class="organization-orders-muted">
                            {{ $order->created_at?->format('d.m.Y H:i') }}
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <div class="organization-orders-status">{{ $statusLabels[$order->status] ?? $order->status }}</div>
                        <div class="organization-orders-total">
                            {{ number_format((float) $order->total, 2, ',', ' ') }}
                            {{ $order->currency }}
                        </div>
                    </div>
                </div>

                <div class="organization-orders-lines">
                    @foreach($order->items as $item)
                        <div>
                            {{ $item->item_name }} × {{ $item->quantity }}
                            — {{ number_format((float) $item->line_total, 2, ',', ' ') }} {{ $order->currency }}
                        </div>
                    @endforeach
                </div>

                <div class="organization-orders-muted">
                    Клиент: {{ $order->customer_name }} · {{ $order->customer_phone }}
                </div>
                <div class="organization-orders-muted">
                    Получение:
                    @switch($order->fulfillment_type)
                        @case('delivery') Доставка @break
                        @case('pickup') Самовывоз @break
                        @case('dine_in') В заведении @break
                        @default {{ $order->fulfillment_type }}
                    @endswitch
                    · Оплата:
                    @switch($order->payment_method)
                        @case('cash_on_receipt') Наличные @break
                        @case('card_on_receipt') Терминал @break
                        @default Онлайн
                    @endswitch
                </div>
                <div class="organization-orders-muted">
                    Состояние оплаты: {{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}
                </div>

                @if($order->delivery_address)
                    <div class="organization-orders-muted" style="margin-top: 7px;">
                        Адрес: {{ $order->delivery_address['address_line'] ?? '' }}
                        @if(!empty($order->delivery_address['entrance']))
                            · подъезд {{ $order->delivery_address['entrance'] }}
                        @endif
                        @if(!empty($order->delivery_address['floor']))
                            · этаж {{ $order->delivery_address['floor'] }}
                        @endif
                        @if(!empty($order->delivery_address['apartment']))
                            · кв./офис {{ $order->delivery_address['apartment'] }}
                        @endif
                    </div>
                    @if(!empty($order->delivery_address['delivery_comment']))
                        <div class="organization-orders-muted">
                            Курьеру: {{ $order->delivery_address['delivery_comment'] }}
                        </div>
                    @endif
                @endif

                @if($order->customer_comment)
                    <div class="organization-orders-muted">
                        Комментарий: {{ $order->customer_comment }}
                    </div>
                @endif

                @foreach($order->assignments->whereNull('unassigned_at') as $assignment)
                    <div class="organization-orders-assignment">
                        {{ $assignment->role === 'cook' ? 'Повар' : 'Курьер' }}:
                        {{ $assignment->employee?->name ?? 'Сотрудник удалён' }}
                    </div>
                @endforeach

                @if($order->statusHistory->isNotEmpty())
                    <details style="margin-top: 12px;">
                        <summary style="color: #00e5d4; cursor: pointer;">
                            История заказа ({{ $order->statusHistory->count() }})
                        </summary>
                        <div style="margin-top: 8px; padding-left: 12px; border-left: 2px solid rgba(0, 229, 212, .35);">
                            @foreach($order->statusHistory as $history)
                                <div style="padding: 7px 0;">
                                    <div class="organization-orders-muted">
                                        {{ $history->created_at?->format('d.m.Y H:i:s') }}
                                        · {{ $statusLabels[$history->to_status] ?? $history->to_status }}
                                        · {{ $history->actor_type === 'employee' ? 'Сотрудник' : ($history->actor_type === 'customer' ? 'Клиент' : ($history->actor_type === 'user' ? 'Менеджер' : 'Система')) }}
                                    </div>
                                    @if($history->comment)
                                        <div class="organization-orders-muted">{{ $history->comment }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif

                @php
                    $canCollectOfflinePayment = match ($order->fulfillment_type) {
                        'delivery' => $order->status === 'delivered',
                        'pickup' => $order->status === 'issued',
                        'dine_in' => $order->status === 'served',
                        default => false,
                    };
                @endphp

                <div class="organization-orders-actions">
                    @if($order->status === 'new')
                        <form method="POST" action="{{ route('organizations.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="confirmed">
                            <button type="submit" class="organization-orders-button">
                                Подтвердить заказ
                            </button>
                        </form>
                    @endif

                    @if(in_array($order->status, ['new', 'confirmed', 'preparing', 'ready'], true) && $order->payment_status !== 'succeeded')
                        <form
                            method="POST"
                            action="{{ route('organizations.orders.status', [$organization, $order]) }}"
                            onsubmit="return confirm('Отменить заказ №{{ $order->order_number }}?');"
                        >
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="organization-orders-button organization-orders-danger">
                                Отменить
                            </button>
                        </form>
                    @endif

                    @if($order->status === 'ready' && $order->fulfillment_type === 'pickup')
                        <form method="POST" action="{{ route('organizations.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="issued">
                            <button type="submit" class="organization-orders-button">Отметить выданным</button>
                        </form>
                    @endif

                    @if($order->status === 'ready' && $order->fulfillment_type === 'dine_in')
                        <form method="POST" action="{{ route('organizations.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="served">
                            <button type="submit" class="organization-orders-button">Отметить поданным</button>
                        </form>
                    @endif

                    @if(in_array($order->status, ['delivered', 'issued', 'served'], true) && $order->payment_status === 'succeeded')
                        <form method="POST" action="{{ route('organizations.orders.status', [$organization, $order]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="organization-orders-button">Завершить заказ</button>
                        </form>
                    @endif

                    @if($order->payment_status === 'pending' && in_array($order->payment_method, ['cash_on_receipt', 'card_on_receipt'], true) && $canCollectOfflinePayment)
                        <form
                            method="POST"
                            action="{{ route('organizations.orders.payment', [$organization, $order]) }}"
                            onsubmit="return confirm('Подтвердить получение оплаты по заказу №{{ $order->order_number }}?');"
                        >
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="organization-orders-button">
                                Отметить оплату полученной
                            </button>
                        </form>
                    @endif

                    @if(!in_array($order->status, ['cancelled', 'delivered', 'completed'], true))
                        <form method="POST" action="{{ route('organizations.orders.assign', [$organization, $order]) }}">
                            @csrf
                            <input type="hidden" name="role" value="cook">
                            <select name="employee_id" class="organization-orders-select" required>
                                <option value="">Назначить повара</option>
                                @foreach($staffMemberships->where('role', 'cook') as $membership)
                                    @if($membership->employee)
                                        <option value="{{ $membership->employee_id }}">
                                            {{ $membership->employee->name }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="submit" class="organization-orders-button">Назначить</button>
                        </form>

                        @if($order->fulfillment_type === 'delivery')
                            <form method="POST" action="{{ route('organizations.orders.assign', [$organization, $order]) }}">
                                @csrf
                                <input type="hidden" name="role" value="courier">
                                <select name="employee_id" class="organization-orders-select" required>
                                    <option value="">Назначить курьера</option>
                                    @foreach($staffMemberships->where('role', 'courier') as $membership)
                                        @if($membership->employee)
                                            <option value="{{ $membership->employee_id }}">
                                                {{ $membership->employee->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <button type="submit" class="organization-orders-button">Назначить</button>
                            </form>
                        @endif
                    @endif
                </div>
            </article>
        @empty
            <div class="organization-orders-muted">
                Заказов пока нет.
            </div>
        @endforelse

        {{ $orders->links() }}
    </div>
</div>

<script>
(() => {
    const page = document.querySelector('.organization-orders-page[data-orders-updates-url]');
    let list = document.getElementById('organization-orders-list');

    if (!page || !list) {
        return;
    }

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
                throw new Error('Не удалось обновить список заказов.');
            }

            const html = await response.text();
            const documentFromServer = new DOMParser().parseFromString(html, 'text/html');
            const updatedList = documentFromServer.getElementById('organization-orders-list');

            if (!updatedList) {
                throw new Error('Сервер вернул некорректный ответ.');
            }

            if (list.innerHTML !== updatedList.innerHTML) {
                const replacement = document.importNode(updatedList, true);
                list.replaceWith(replacement);
                list = replacement;
            }
        } catch (error) {
            console.warn('Organization orders polling:', error);
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
