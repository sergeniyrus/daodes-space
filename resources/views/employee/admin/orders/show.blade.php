@extends('organizations.layouts.management')

@section('title_page') Заказ №{{ $order->order_number }} — {{ $organization->name }} @endsection

@section('management-content')
@include('components.order-notifications', [
    'notificationsUrl' => route('employee.organization.admin.orders.notifications', $organization),
    'notificationReadUrlTemplate' => route('employee.organization.admin.orders.notifications.read', [$organization, '__ID__']),
])


@php
$statusLabels=['new'=>'Новый','awaiting_payment'=>'Ожидает оплаты','confirmed'=>'Подтверждён','preparing'=>'Готовится','ready'=>'Готов','handed_to_courier'=>'Передан курьеру','in_transit'=>'В пути','issued'=>'Выдан','served'=>'Подан','delivered'=>'Доставлен','completed'=>'Завершён','cancelled'=>'Отменён'];
$paymentLabels=['pending'=>'Ожидает оплаты','succeeded'=>'Оплачен','failed'=>'Ошибка оплаты','cancelled'=>'Отменён','refunded'=>'Возвращён'];
$methodLabels=['online'=>'Онлайн','cash_on_receipt'=>'Наличные при получении','card_on_receipt'=>'Картой при получении'];
$fulfillmentLabels=['delivery'=>'Доставка','pickup'=>'Самовывоз','dine_in'=>'В заведении'];
$collectable = ($order->fulfillment_type === 'pickup' && $order->status === 'issued') || ($order->fulfillment_type === 'delivery' && $order->status === 'delivered') || ($order->fulfillment_type === 'dine_in' && $order->status === 'served');
@endphp

<style>
.admin-order-page{--gold:#ffd700;--cyan:#00e5d4;--muted:#aeb5c4;--line:rgba(0,229,212,.2);width:100%;max-width:none;margin:0;color:#f4f5fa}
.admin-order-page *{box-sizing:border-box}
.admin-order-shell{padding:clamp(16px,2.5vw,27px);margin-bottom:15px;background:radial-gradient(circle at 100% 0,rgba(255,215,0,.065),transparent 32%),linear-gradient(145deg,#0b0c18,#111626 58%,#0b0c18);border:1px solid rgba(255,215,0,.38);border-radius:20px;box-shadow:0 14px 42px rgba(0,0,0,.32)}
.admin-order-top{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid rgba(255,215,0,.2)}
.admin-order-crumbs{display:flex;gap:9px;flex-wrap:wrap}
.admin-order-page a{color:var(--cyan);text-decoration:none}
.admin-order-page a:hover{color:var(--gold)}
.admin-order-eyebrow{margin:0 0 6px;color:var(--cyan);font-size:.82rem;letter-spacing:.07em;text-transform:uppercase}
.admin-order-page h1,.admin-order-page h2{color:var(--gold);margin:0 0 15px}
.admin-order-page h1{font-size:clamp(1.5rem,3vw,2rem)}
.admin-order-page h2{font-size:1.2rem}
.admin-order-page h3{color:var(--gold);font-size:1rem;margin:0 0 10px}
.admin-order-state{display:inline-flex;padding:7px 12px;border:1px solid rgba(0,229,212,.35);border-radius:20px;color:var(--cyan);font-size:.85rem}
.admin-order-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.admin-order-fact{padding:13px;border:1px solid rgba(0,229,212,.2);border-radius:12px;background:rgba(8,13,24,.55);min-width:0}
.admin-order-fact span{display:block;color:var(--muted);font-size:.8rem;margin-bottom:7px}
.admin-order-fact strong{display:block;overflow-wrap:anywhere;color:#f4f5fa}
.admin-order-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.admin-order-info{padding:15px;border:1px solid var(--line);border-radius:13px;background:rgba(8,13,24,.42)}
.admin-order-info p{margin:7px 0;line-height:1.55}
.admin-order-muted{color:var(--muted)}
.admin-order-items{width:100%;border-collapse:collapse}
.admin-order-items td{padding:12px 5px;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:top}
.admin-order-items td:last-child{text-align:right;white-space:nowrap;color:var(--gold);font-weight:600}
.admin-order-total{display:flex;justify-content:space-between;gap:12px;padding-top:16px;margin-top:8px;font-size:1.2rem}
.admin-order-total strong{color:var(--gold);font-variant-numeric:tabular-nums}
.admin-order-button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 15px;border:1px solid var(--cyan);border-radius:11px;background:rgba(0,229,212,.06);color:var(--cyan);font-weight:600;cursor:pointer;transition:.2s}
.admin-order-button:hover{background:var(--cyan);color:#071316}
.admin-order-button.gold{border-color:var(--gold);color:var(--gold);background:rgba(255,215,0,.05)}
.admin-order-button.gold:hover{background:var(--gold);color:#171506}
.admin-order-page select{width:min(100%,390px);min-height:42px;padding:8px 11px;background:#0b1220;color:#fff;border:1px solid #39495a;border-radius:10px}
.admin-order-action-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.admin-order-entry{padding:11px 0;border-top:1px solid rgba(0,229,212,.14);line-height:1.5}
.admin-order-entry:first-of-type{border-top:0}
.admin-order-entry small{display:block;color:var(--muted);margin-top:3px}
.admin-order-note{padding:12px 14px;border:1px solid rgba(255,215,0,.22);border-radius:11px;background:rgba(255,215,0,.045);color:#e9dfb4}
@media(max-width:760px){.admin-order-facts{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-order-grid{grid-template-columns:1fr}}
@media(max-width:520px){.admin-order-page{width:calc(100% - 16px);margin:14px auto 30px}.admin-order-shell{padding:15px;border-radius:16px}.admin-order-facts{gap:8px}.admin-order-fact{padding:11px}.admin-order-total{font-size:1.05rem}}
</style>
<style>@media(max-width:520px){.admin-order-page{width:100%;margin:0}}</style>

<div class="admin-order-page">
    <section class="admin-order-shell">
        <div class="admin-order-top">
            <div class="admin-order-crumbs">
                <a href="{{ route('employee.organization.admin.orders.index',$organization) }}">← Все заказы</a>
                <span class="admin-order-muted">/</span>
                <a href="{{ route('employee.organization.menu.index',$organization) }}">Управление меню</a>
            </div>
            <span class="admin-order-state">Панель администратора</span>
        </div>
        <p class="admin-order-eyebrow">ДЕТАЛИ ЗАКАЗА</p>
        <h1>Заказ №{{ $order->order_number }}</h1>
        <div class="admin-order-facts">
            <div class="admin-order-fact"><span>Дата создания</span><strong>{{ $order->created_at?->format('d.m.Y H:i:s') }}</strong></div>
            <div class="admin-order-fact"><span>Статус заказа</span><strong>{{ $statusLabels[$order->status] ?? $order->status }}</strong></div>
            <div class="admin-order-fact"><span>Способ получения</span><strong>{{ $fulfillmentLabels[$order->fulfillment_type] ?? $order->fulfillment_type }}</strong></div>
            <div class="admin-order-fact"><span>Состояние оплаты</span><strong>{{ $order->payment_method === 'guest_account' && $order->payment_status === 'pending' ? 'Ожидает расчёта на кассе при выходе' : ($paymentLabels[$order->payment_status] ?? $order->payment_status) }}</strong></div>
        </div>
    </section>

    <div class="admin-order-grid">
        <section class="admin-order-shell">
            <h2>Информация о клиенте</h2>
            <div class="admin-order-info">
                <h3>{{ $order->customer_name }}</h3>
                <p>Телефон: <a href="tel:{{ preg_replace('/[^0-9+]/','',$order->customer_phone) }}">{{ $order->customer_phone }}</a></p>
                @if($order->fulfillment_type === 'delivery')
                    <p>Адрес доставки: {{ $order->delivery_address['address_line'] ?? '—' }}</p>
                @endif
                @if($order->customer_comment)
                    <p>Комментарий к заказу: {{ $order->customer_comment }}</p>
                @endif
            </div>
        </section>

        <section class="admin-order-shell">
            <h2>Оплата</h2>
            <div class="admin-order-info">
                <p><strong>{{ $order->payment_method === 'guest_account' ? 'На счёт гостя — расчёт при выходе' : ($methodLabels[$order->payment_method] ?? $order->payment_method) }}</strong></p>
                <p class="admin-order-muted">{{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}</p>
                @if($order->payment_status === 'pending' && $order->payment_method !== 'online'
                && $order->payment_method !== 'guest_account' && $collectable)
                    <form method="POST" action="{{ route('employee.organization.admin.orders.payment',[$organization,$order]) }}" onsubmit="return confirm('Подтвердить фактическое получение оплаты?')">
                        @csrf
                        @method('PATCH')
                        <button class="admin-order-button gold" type="submit">✓ Подтвердить получение оплаты</button>
                    </form>
                @elseif($order->payment_status === 'pending' && $order->payment_method !== 'online')
                    <div class="admin-order-note">Подтверждение станет доступно после фактической передачи заказа клиенту.</div>
                @endif
                @foreach($order->payments as $payment)
                    <div class="admin-order-entry">
                        {{ $payment->provider }} · {{ $payment->method }}
                        <small>{{ $paymentLabels[$payment->status] ?? $payment->status }} · {{ $payment->amount }} {{ $payment->currency }}</small>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="admin-order-shell">
        <h2>Состав заказа</h2>
        <table class="admin-order-items">
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->item_name }} <span class="admin-order-muted">× {{ $item->quantity }}</span></td>
                        <td>{{ number_format((float)$item->line_total,2,',',' ') }} {{ $order->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="admin-order-total"><span>Итого к оплате</span><strong>{{ number_format((float)$order->total,2,',',' ') }} {{ $order->currency }}</strong></div>
    </section>

    <section class="admin-order-shell">
        <h2>Управление заказом</h2>
        @if(in_array($order->status,['ready','delivered','issued','served','new','confirmed','preparing'],true))
            <form class="admin-order-action-row" method="POST" action="{{ route('employee.organization.admin.orders.status',[$organization,$order]) }}">
                @csrf
                @method('PATCH')
                <select name="status" required>
                    <option value="">Выберите действие</option>
                    <option value="served">Отметить как поданный (для зала)</option>
                    <option value="completed">Завершить заказ (после оплаты)</option>
                    <option value="cancelled">Отменить заказ</option>
                </select>
                <button class="admin-order-button" type="submit">Применить</button>
            </form>
        @else
            <p class="admin-order-muted">Для текущего состояния дополнительных действий нет.</p>
        @endif
    </section>

    <section class="admin-order-shell">
        <h2>Назначения и история заказа</h2>
        @forelse($order->assignments as $assignment)
            <div class="admin-order-entry">
                <strong>{{ $assignment->role }}</strong>: {{ $assignment->employee?->name ?? '—' }}
                <small>Назначено: {{ $assignment->assigned_at?->format('d.m.Y H:i') }}</small>
            </div>
        @empty
            <p class="admin-order-muted">Назначений пока нет.</p>
        @endforelse

        @forelse($order->statusHistory as $history)
            <div class="admin-order-entry">
                <strong>{{ $statusLabels[$history->to_status] ?? $history->to_status }}</strong>
                <small>{{ $history->created_at?->format('d.m.Y H:i:s') }} · {{ $history->comment }}</small>
            </div>
        @empty
            <p class="admin-order-muted">История изменений пока пуста.</p>
        @endforelse
    </section>
</div>
@endsection
