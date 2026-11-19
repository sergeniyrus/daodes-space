@extends('template')

@section('title_page') Заказ №{{ $order->order_number }} — {{ $organization->name }} @endsection

@section('main')
@include('components.order-notifications', [
    'notificationsUrl' => route('employee.organization.admin.orders.notifications', $organization),
    'notificationReadUrlTemplate' => route('employee.organization.admin.orders.notifications.read', [$organization, '__ID__']),
])

@php
$statusLabels=['new'=>'Новый','awaiting_payment'=>'Ожидает оплаты','confirmed'=>'Подтверждён','preparing'=>'Готовится','ready'=>'Готов','handed_to_courier'=>'Передан курьеру','in_transit'=>'В пути','issued'=>'Выдан','served'=>'Подан','delivered'=>'Доставлен','completed'=>'Завершён','cancelled'=>'Отменён'];
$paymentLabels=['pending'=>'Ожидает оплаты','succeeded'=>'Оплачен','failed'=>'Ошибка оплаты','cancelled'=>'Отменён','refunded'=>'Возвращён'];
$methodLabels=['online'=>'Онлайн','cash_on_receipt'=>'Наличные при получении','card_on_receipt'=>'Картой при получении'];
$collectable = ($order->fulfillment_type === 'pickup' && $order->status === 'issued') || ($order->fulfillment_type === 'delivery' && $order->status === 'delivered') || ($order->fulfillment_type === 'dine_in' && $order->status === 'served');
@endphp
<style>
.admin-order-detail{width:min(1000px,calc(100% - 24px));margin:25px auto;color:#f4f4f4}.admin-order-panel{padding:18px;margin-bottom:14px;background:linear-gradient(145deg,#0b0c18,#14182a);border:1px solid rgba(255,215,0,.4);border-radius:15px}.admin-order-detail h1,.admin-order-detail h2{color:#ffd700}.admin-order-detail a{color:#00e5d4}.admin-order-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}.admin-order-entry{padding:10px 0;border-top:1px solid rgba(0,229,212,.2)}.muted{color:#aeb5c4}.admin-order-btn{padding:9px 14px;border:1px solid #00e5d4;border-radius:18px;background:transparent;color:#00e5d4;cursor:pointer}.admin-order-detail select{padding:8px;background:#101522;color:#fff;border:1px solid #52606f;border-radius:8px}
</style>
<div class="admin-order-detail">
 <section class="admin-order-panel">
  <p><a href="{{ route('employee.organization.admin.orders.index',$organization) }}">← Все заказы</a> · <a href="{{ route('employee.organization.menu.index',$organization) }}">Управление меню</a></p>
  <h1>Заказ №{{ $order->order_number }}</h1>
  <div class="admin-order-grid">
   <div>Создан<br><strong>{{ $order->created_at?->format('d.m.Y H:i:s') }}</strong></div>
   <div>Статус<br><strong>{{ $statusLabels[$order->status] ?? $order->status }}</strong></div>
   <div>Получение<br><strong>{{ $order->fulfillment_type }}</strong></div>
   <div>Оплата<br><strong>{{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}</strong></div>
  </div>
 </section>
 <section class="admin-order-panel">
  <h2>Клиент</h2>
  <p>{{ $order->customer_name }} · <a href="tel:{{ preg_replace('/[^0-9+]/','',$order->customer_phone) }}">{{ $order->customer_phone }}</a></p>
  @if($order->fulfillment_type === 'delivery')
   <p>Адрес: {{ $order->delivery_address['address_line'] ?? '—' }}</p>
  @endif
  @if($order->customer_comment)<p>Комментарий: {{ $order->customer_comment }}</p>@endif
 </section>
 <section class="admin-order-panel">
  <h2>Состав заказа</h2>
  @foreach($order->items as $item)<div class="admin-order-entry">{{ $item->item_name }} × {{ $item->quantity }} <span style="float:right">{{ number_format((float)$item->line_total,2,',',' ') }} {{ $order->currency }}</span></div>@endforeach
  <h2>Итого: {{ number_format((float)$order->total,2,',',' ') }} {{ $order->currency }}</h2>
 </section>
 <section class="admin-order-panel">
  <h2>Оплата</h2>
  <p>{{ $methodLabels[$order->payment_method] ?? $order->payment_method }} · {{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}</p>
  @if($order->payment_status === 'pending' && $order->payment_method !== 'online' && $collectable)
   <form method="POST" action="{{ route('employee.organization.admin.orders.payment',[$organization,$order]) }}" onsubmit="return confirm('Подтвердить фактическое получение оплаты?')">@csrf @method('PATCH')<button class="admin-order-btn">✓ Подтвердить получение оплаты</button></form>
  @elseif($order->payment_status === 'pending' && $order->payment_method !== 'online')
   <p class="muted">Подтверждение станет доступно после фактической передачи заказа клиенту.</p>
  @endif
  @foreach($order->payments as $payment)<div class="admin-order-entry">{{ $payment->provider }} · {{ $payment->method }} · {{ $paymentLabels[$payment->status] ?? $payment->status }} · {{ $payment->amount }} {{ $payment->currency }}</div>@endforeach
 </section>
 <section class="admin-order-panel">
  <h2>Действия администратора</h2>
  @if(in_array($order->status,['ready','delivered','issued','served','new','confirmed','preparing'],true))
   <form method="POST" action="{{ route('employee.organization.admin.orders.status',[$organization,$order]) }}">@csrf @method('PATCH')
    <select name="status" required><option value="">Выберите действие</option><option value="served">Отметить как поданный (для зала)</option><option value="completed">Завершить заказ (после оплаты)</option><option value="cancelled">Отменить заказ</option></select>
    <button class="admin-order-btn" type="submit">Применить</button>
   </form>
  @else
   <p class="muted">Для текущего состояния дополнительных действий нет.</p>
  @endif
 </section>
 <section class="admin-order-panel">
  <h2>Назначения и история</h2>
  @foreach($order->assignments as $assignment)<div class="admin-order-entry">{{ $assignment->role }}: {{ $assignment->employee?->name ?? '—' }} · {{ $assignment->assigned_at?->format('d.m.Y H:i') }}</div>@endforeach
  @foreach($order->statusHistory as $history)<div class="admin-order-entry">{{ $history->created_at?->format('d.m.Y H:i:s') }} · {{ $statusLabels[$history->to_status] ?? $history->to_status }} · {{ $history->comment }}</div>@endforeach
 </section>
</div>
@endsection
