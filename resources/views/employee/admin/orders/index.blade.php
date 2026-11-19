@extends('template')

@section('title_page') Заказы — {{ $organization->name }} @endsection

@section('main')
@include('components.order-notifications', [
    'notificationsUrl' => route('employee.organization.admin.orders.notifications', $organization),
    'notificationReadUrlTemplate' => route('employee.organization.admin.orders.notifications.read', [$organization, '__ID__']),
])

@php
$statusLabels = ['new'=>'Новый','awaiting_payment'=>'Ожидает оплаты','confirmed'=>'Подтверждён','preparing'=>'Готовится','ready'=>'Готов','handed_to_courier'=>'У курьера','in_transit'=>'В пути','issued'=>'Выдан','served'=>'Подан','delivered'=>'Доставлен','completed'=>'Завершён','cancelled'=>'Отменён'];
$paymentLabels = ['pending'=>'Ожидает оплаты','succeeded'=>'Оплачен','failed'=>'Ошибка','cancelled'=>'Отменён','refunded'=>'Возвращён'];
@endphp
<style>
.admin-orders{width:min(1100px,calc(100% - 24px));margin:25px auto;color:#f4f4f4}.admin-orders-panel{padding:18px;margin-bottom:14px;background:linear-gradient(145deg,#0b0c18,#14182a);border:1px solid rgba(255,215,0,.4);border-radius:15px}.admin-orders h1,.admin-orders h2{color:#ffd700}.admin-orders a{color:#00e5d4}.admin-orders form.filters{display:flex;gap:8px;flex-wrap:wrap}.admin-orders input,.admin-orders select{min-height:38px;padding:6px 10px;color:#fff;background:#101522;border:1px solid #52606f;border-radius:8px}.admin-order{padding:14px 0;border-top:1px solid rgba(0,229,212,.2)}.admin-order:first-child{border:0}.admin-order-head{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}.muted{color:#aeb5c4}.admin-btn{display:inline-block;padding:8px 13px;border:1px solid #00e5d4;border-radius:18px;text-decoration:none;background:transparent;color:#00e5d4!important}
</style>
<div class="admin-orders">
 <section class="admin-orders-panel">
  <p class="muted">{{ $organization->name }} · Администратор</p>
  <h1>Заказы организации</h1>
  <p><a href="{{ route('employee.organization.menu.index', $organization) }}">← Управление меню</a></p>
  <form class="filters" method="GET">
   <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Номер, имя или телефон">
   <select name="status"><option value="">Все статусы</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
   <select name="payment_status"><option value="">Любая оплата</option>@foreach($paymentLabels as $key=>$label)<option value="{{ $key }}" @selected(($filters['payment_status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
   <button class="admin-btn" type="submit">Фильтровать</button>
   <a class="admin-btn" href="{{ route('employee.organization.admin.orders.index', $organization) }}">Сбросить</a>
  </form>
 </section>
 <section class="admin-orders-panel">
  @forelse($orders as $order)
   <article class="admin-order">
    <div class="admin-order-head"><strong>Заказ №{{ $order->order_number }}</strong><span>{{ $statusLabels[$order->status] ?? $order->status }}</span></div>
    <div>{{ $order->customer_name }} · {{ $order->customer_phone }}</div>
    <div class="muted">{{ $order->created_at?->format('d.m.Y H:i') }} · {{ $order->fulfillment_type }} · {{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}</div>
    <div>{{ $order->items->map(fn($item) => $item->item_name.' × '.$item->quantity)->implode(', ') }}</div>
    <div><strong>{{ number_format((float)$order->total,2,',',' ') }} {{ $order->currency }}</strong></div>
    <p><a class="admin-btn" href="{{ route('employee.organization.admin.orders.show', [$organization,$order]) }}">Открыть заказ</a></p>
   </article>
  @empty
   <p class="muted">Заказов по выбранным условиям нет.</p>
  @endforelse
  {{ $orders->links() }}
 </section>
</div>
@endsection
