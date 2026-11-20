@extends('organizations.layouts.management')

@section('title_page')
    Заказ №{{ $order->order_number }} — {{ $organization->name }}
@endsection

@section('management-content')
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

    $fulfillmentLabels = [
        'delivery' => 'Доставка',
        'pickup' => 'Самовывоз',
        'dine_in' => 'В заведении',
    ];

    $methodLabels = [
        'online' => 'Онлайн',
        'cash_on_receipt' => 'Наличные при получении',
        'card_on_receipt' => 'Картой через терминал',
    ];
@endphp

<style>
.organization-order-detail {
    width: 100%;
    max-width: none;
    margin: 0;
    color: #f4f4f4;
}
.organization-order-detail__panel {
    padding: 20px;
    margin-bottom: 16px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .42);
    border-radius: 16px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .3);
}
.organization-order-detail__title { margin: 0 0 8px; color: #ffd700; }
.organization-order-detail__subtitle { color: #aeb5c4; }
.organization-order-detail__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 22px;
}
.organization-order-detail__field { min-width: 0; padding: 8px 0; border-bottom: 1px solid rgba(0, 229, 212, .15); }
.organization-order-detail__label { display: block; color: #9faabd; font-size: .82rem; margin-bottom: 4px; }
.organization-order-detail__value { color: #f4f4f4; overflow-wrap: anywhere; }
.organization-order-detail__accent { color: #00e5d4; font-weight: 700; }
.organization-order-detail__total { color: #ffd700; font-size: 1.25rem; font-weight: 800; }
.organization-order-detail__list { display: grid; gap: 10px; }
.organization-order-detail__entry {
    padding: 12px 0;
    border-top: 1px solid rgba(0, 229, 212, .18);
}
.organization-order-detail__entry:first-child { border-top: 0; }
.organization-order-detail__actions { display: flex; flex-wrap: wrap; gap: 10px; }
.organization-order-detail__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 8px 15px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .06);
    border: 1px solid rgba(0, 229, 212, .65);
    border-radius: 22px;
    text-decoration: none;
    cursor: pointer;
    font-weight: 700;
}
.organization-order-detail__button:hover { color: #081016; background: #00e5d4; }
.organization-order-detail__empty { color: #9faabd; }
@media(max-width:650px) {
    .organization-order-detail__panel { padding: 15px; }
    .organization-order-detail__grid { grid-template-columns: 1fr; }
    .organization-order-detail__actions { flex-direction: column; }
}
@media print {
    body, main, #main { background: #fff !important; color: #111 !important; }
    .organization-order-detail { width: 100%; margin: 0; color: #111; }
    .organization-order-detail__panel {
        background: #fff !important;
        color: #111 !important;
        border: 1px solid #bbb;
        box-shadow: none;
        break-inside: avoid;
    }
    .organization-order-detail__title,
    .organization-order-detail__total,
    .organization-order-detail__accent,
    .organization-order-detail__value { color: #111 !important; }
    .organization-order-detail__subtitle,
    .organization-order-detail__label,
    .organization-order-detail__empty { color: #444 !important; }
    .organization-order-detail__actions,
    .no-print { display: none !important; }
}
</style>

<div class="organization-order-detail">
    <div class="organization-order-detail__panel no-print">
        <div class="organization-order-detail__actions">
            <a href="{{ route('organizations.orders.index', $organization) }}" class="organization-order-detail__button">
                ← К списку заказов
            </a>
            <a
                href="{{ route('organizations.orders.receipt', [$organization, $order, 'format' => '80mm']) }}"
                class="organization-order-detail__button"
                target="_blank"
                rel="noopener"
            >
                Чек 80 мм
            </a>
            <a
                href="{{ route('organizations.orders.receipt', [$organization, $order, 'format' => 'a4']) }}"
                class="organization-order-detail__button"
                target="_blank"
                rel="noopener"
            >
                Чек A4
            </a>
            <button type="button" class="organization-order-detail__button" onclick="window.print()">
                Распечатать заказ
            </button>
        </div>
    </div>

    <section class="organization-order-detail__panel">
        <div class="organization-order-detail__subtitle">{{ $organization->name }}</div>
        <h1 class="organization-order-detail__title">Заказ №{{ $order->order_number }}</h1>
        <div class="organization-order-detail__grid">
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Создан</span>
                <div class="organization-order-detail__value">{{ $order->created_at?->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i:s') ?? '—' }}</div>
            </div>
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Статус заказа</span>
                <div class="organization-order-detail__value organization-order-detail__accent">
                    {{ $statusLabels[$order->status] ?? $order->status }}
                </div>
            </div>
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Способ получения</span>
                <div class="organization-order-detail__value">{{ $fulfillmentLabels[$order->fulfillment_type] ?? $order->fulfillment_type }}</div>
            </div>
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Желаемое время</span>
                <div class="organization-order-detail__value">{{ $order->requested_at?->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') ?? 'Не указано' }}</div>
            </div>
            @if($order->table_number)
                <div class="organization-order-detail__field">
                    <span class="organization-order-detail__label">Номер браслета</span>
                    <div class="organization-order-detail__value">{{ $order->table_number }}</div>
                </div>
            @endif
            @if($order->guest_card_identifier)
                <div class="organization-order-detail__field">
                    <span class="organization-order-detail__label">Браслет / карта сотрудника</span>
                    <div class="organization-order-detail__value">{{ $order->guest_card_identifier }}</div>
                </div>
            @endif
        </div>
    </section>

    <section class="organization-order-detail__panel">
        <h2 class="organization-order-detail__title">Клиент и получение</h2>
        <div class="organization-order-detail__grid">
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Имя клиента</span>
                <div class="organization-order-detail__value">{{ $order->customer_name }}</div>
            </div>
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Телефон</span>
                <div class="organization-order-detail__value">
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->customer_phone) }}" class="organization-order-detail__accent">
                        {{ $order->customer_phone }}
                    </a>
                </div>
            </div>
            @if($order->fulfillment_type === 'pickup')
                <div class="organization-order-detail__field" style="grid-column: 1 / -1;">
                    <span class="organization-order-detail__label">Адрес самовывоза</span>
                    <div class="organization-order-detail__value">{{ $order->pickup_address_snapshot ?: ($organization->orderSettings?->pickup_address ?? 'Адрес не указан') }}</div>
                </div>
            @endif
            @if($order->fulfillment_type === 'delivery')
                <div class="organization-order-detail__field" style="grid-column: 1 / -1;">
                    <span class="organization-order-detail__label">Адрес доставки</span>
                    <div class="organization-order-detail__value">
                        {{ $order->delivery_address['address_line'] ?? 'Адрес не указан' }}
                        @if(!empty($order->delivery_address['entrance'])) · подъезд {{ $order->delivery_address['entrance'] }} @endif
                        @if(!empty($order->delivery_address['floor'])) · этаж {{ $order->delivery_address['floor'] }} @endif
                        @if(!empty($order->delivery_address['apartment'])) · кв./офис {{ $order->delivery_address['apartment'] }} @endif
                        @if(!empty($order->delivery_address['intercom'])) · домофон {{ $order->delivery_address['intercom'] }} @endif
                    </div>
                    @if(!empty($order->delivery_address['delivery_comment']))
                        <div class="organization-order-detail__subtitle" style="margin-top: 6px;">
                            Курьеру: {{ $order->delivery_address['delivery_comment'] }}
                        </div>
                    @endif
                </div>
            @endif
            @if($order->customer_comment)
                <div class="organization-order-detail__field" style="grid-column: 1 / -1;">
                    <span class="organization-order-detail__label">Комментарий клиента</span>
                    <div class="organization-order-detail__value">{{ $order->customer_comment }}</div>
                </div>
            @endif
        </div>
    </section>

    <section class="organization-order-detail__panel">
        <h2 class="organization-order-detail__title">Состав заказа</h2>
        <div class="organization-order-detail__list">
            @foreach($order->items as $item)
                <div class="organization-order-detail__entry">
                    <div class="organization-order-detail__grid">
                        <div class="organization-order-detail__value">
                            {{ $item->item_name }} × {{ $item->quantity }}
                        </div>
                        <div class="organization-order-detail__value" style="text-align: right;">
                            {{ number_format((float) $item->line_total, 2, ',', ' ') }} {{ $order->currency }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="organization-order-detail__grid" style="margin-top: 14px;">
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Товары</span>
                <div class="organization-order-detail__value">{{ number_format((float) $order->subtotal, 2, ',', ' ') }} {{ $order->currency }}</div>
            </div>
            @if($order->fulfillment_type === 'delivery' || (float) $order->delivery_fee > 0)
                <div class="organization-order-detail__field">
                    <span class="organization-order-detail__label">Доставка</span>
                    <div class="organization-order-detail__value">
                        @if((float) $order->delivery_fee > 0)
                            {{ number_format((float) $order->delivery_fee, 2, ',', ' ') }} {{ $order->currency }}
                        @else
                            Без отдельной платы
                        @endif
                    </div>
                </div>
            @endif
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Скидка</span>
                <div class="organization-order-detail__value">{{ number_format((float) $order->discount_total, 2, ',', ' ') }} {{ $order->currency }}</div>
            </div>
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Итого</span>
                <div class="organization-order-detail__total">{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</div>
            </div>
        </div>
    </section>

    <section class="organization-order-detail__panel">
        <h2 class="organization-order-detail__title">Оплата</h2>
        <div class="organization-order-detail__grid">
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Способ оплаты</span>
                <div class="organization-order-detail__value">{{ $order->payment_method === 'guest_account' ? 'На счёт гостя — расчёт при выходе' : ($methodLabels[$order->payment_method] ?? $order->payment_method) }}</div>
            </div>
            <div class="organization-order-detail__field">
                <span class="organization-order-detail__label">Состояние оплаты</span>
                <div class="organization-order-detail__value organization-order-detail__accent">{{ $order->payment_method === 'guest_account' && $order->payment_status === 'pending' ? 'Ожидает расчёта на кассе при выходе' : ($paymentLabels[$order->payment_status] ?? $order->payment_status) }}</div>
            </div>
        
        </div>

        @if($order->payment_method === 'guest_account')
            <p class="organization-order-detail__subtitle" style="margin-top:12px;">
                Расчёт предполагается на кассе при выходе. Номер браслета или карты сохранён в заказе DAODES; автоматическая передача и подтверждение оплаты через iiko будут подключены отдельно. Статус оплаты вручную не меняйте.
            </p>
        @endif

        @php
            $canMarkPaid =
                $order->payment_status === 'pending'
                && $order->payment_method !== 'online'
                && $order->payment_method !== 'guest_account'
                && (
                    ($order->fulfillment_type === 'pickup' && $order->status === 'issued')
                    || ($order->fulfillment_type === 'delivery' && $order->status === 'delivered')
                    || ($order->fulfillment_type === 'dine_in' && $order->status === 'served')
                );
        @endphp

        @if($canMarkPaid)
            <div class="organization-order-detail__entry">
                <div class="organization-order-detail__subtitle" style="margin-bottom: 12px;">
                    Заказ передан клиенту. Если деньги фактически получены, подтвердите оплату.
                </div>

                <form
                    method="POST"
                    action="{{ route('organizations.orders.payment', [$organization, $order]) }}"
                    onsubmit="return confirm('Подтвердить фактическое получение оплаты за заказ №{{ $order->order_number }}?');"
                >
                    @csrf
                    @method('PATCH')

                    <button type="submit" class="organization-order-detail__button">
                        ✓ Подтвердить получение оплаты
                    </button>
                </form>
            </div>
        @elseif($order->payment_status === 'pending' && $order->payment_method !== 'online' && $order->payment_method !== 'guest_account')
            <div class="organization-order-detail__subtitle" style="margin-top: 12px;">
                Подтверждение оплаты станет доступно после фактической передачи заказа клиенту.
            </div>
        @endif

        <h3 class="organization-order-detail__subtitle" style="margin-top: 18px;">Платёжные попытки</h3>

        @forelse($order->payments->sortByDesc('created_at') as $payment)
            <div class="organization-order-detail__entry">
                <div class="organization-order-detail__grid">
                    <div>
                        <div class="organization-order-detail__value">{{ $payment->provider ?: 'Без провайдера' }} · {{ $payment->method }}</div>
                        <div class="organization-order-detail__subtitle">
                            {{ $paymentLabels[$payment->status] ?? $payment->status }}
                            @if($payment->created_at) · {{ $payment->created_at->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i:s') }} @endif
                        </div>
                        @if($payment->paid_at)
                            <div class="organization-order-detail__subtitle">Оплачено: {{ $payment->paid_at->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i:s') }}</div>
                        @endif
                        @if($payment->failed_at)
                            <div class="organization-order-detail__subtitle">Ошибка: {{ $payment->failed_at->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i:s') }}</div>
                        @endif
                        @if($payment->refunded_at)
                            <div class="organization-order-detail__subtitle">Возврат: {{ $payment->refunded_at->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i:s') }}</div>
                        @endif
                    </div>
                    <div class="organization-order-detail__value" style="text-align: right;">
                        {{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}
                    </div>
                </div>
            </div>
        @empty
            <div class="organization-order-detail__empty">Платёжные попытки отсутствуют.</div>
        @endforelse
    </section>

    <section class="organization-order-detail__panel">
        <h2 class="organization-order-detail__title">Управление заказом</h2>

        @if(in_array($order->status, ['ready', 'delivered', 'issued', 'served', 'new', 'confirmed', 'preparing'], true))
            <form
                method="POST"
                action="{{ route('organizations.orders.status', [$organization, $order]) }}"
                class="organization-order-detail__actions"
                onsubmit="return confirm('Применить изменение статуса заказа №{{ $order->order_number }}?');"
            >
                @csrf
                @method('PATCH')

                <select
                    name="status"
                    required
                    style="min-height: 40px; min-width: 230px; padding: 8px 12px; color: #f4f4f4; background: #090c16; border: 1px solid rgba(0, 229, 212, .45); border-radius: 10px;"
                >
                    <option value="">Выберите действие</option>
                    <option value="served">Отметить как поданный (для зала)</option>
                    <option value="completed">Завершить заказ (после оплаты)</option>
                    <option value="cancelled">Отменить заказ</option>
                </select>

                <button type="submit" class="organization-order-detail__button">
                    Применить
                </button>
            </form>
        @else
            <div class="organization-order-detail__empty">
                Для текущего состояния дополнительных действий нет.
            </div>
        @endif
    </section>

    <section class="organization-order-detail__panel">
        <h2 class="organization-order-detail__title">Назначенные сотрудники</h2>
        @forelse($order->assignments->sortByDesc('assigned_at') as $assignment)
            <div class="organization-order-detail__entry">
                <div class="organization-order-detail__value">
                    {{ $assignment->role === 'cook' ? 'Повар' : 'Курьер' }}:
                    {{ $assignment->employee?->name ?? 'Сотрудник удалён' }}
                </div>
                <div class="organization-order-detail__subtitle">
                    Назначен: {{ $assignment->assigned_at?->format('d.m.Y H:i:s') ?? '—' }}
                    @if($assignment->assignedBy)
                        · менеджер: {{ $assignment->assignedBy->name }}
                    @endif
                </div>
                @if($assignment->unassigned_at)
                    <div class="organization-order-detail__subtitle">Снят: {{ $assignment->unassigned_at->format('d.m.Y H:i:s') }}</div>
                @else
                    <div class="organization-order-detail__accent">Активное назначение</div>
                @endif
            </div>
        @empty
            <div class="organization-order-detail__empty">Назначения ещё не создавались.</div>
        @endforelse
    </section>

    <section class="organization-order-detail__panel">
        <h2 class="organization-order-detail__title">История заказа</h2>
        @forelse($order->statusHistory as $history)
            <div class="organization-order-detail__entry">
                <div class="organization-order-detail__value organization-order-detail__accent">
                    {{ $statusLabels[$history->to_status] ?? $history->to_status }}
                </div>
                <div class="organization-order-detail__subtitle">
                    {{ $history->created_at?->format('d.m.Y H:i:s') ?? '—' }}
                    · {{ $history->actor_type === 'employee' ? 'Сотрудник' : ($history->actor_type === 'customer' ? 'Клиент' : ($history->actor_type === 'user' ? 'Менеджер' : 'Система')) }}
                    @if($history->from_status)
                        · {{ $statusLabels[$history->from_status] ?? $history->from_status }}
                        →
                        {{ $statusLabels[$history->to_status] ?? $history->to_status }}
                    @endif
                </div>
                @if($history->comment)
                    <div class="organization-order-detail__value" style="margin-top: 5px;">{{ $history->comment }}</div>
                @endif
            </div>
        @empty
            <div class="organization-order-detail__empty">История пока пуста.</div>
        @endforelse
    </section>
</div>
@endsection
