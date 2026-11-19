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
$fulfillmentLabels = ['delivery'=>'Доставка','pickup'=>'Самовывоз','dine_in'=>'В заведении'];
$paymentMethodLabels = ['online'=>'Онлайн','cash_on_receipt'=>'Наличные при получении','card_on_receipt'=>'Картой при получении'];
@endphp
<style>
.admin-orders{width:min(1100px,calc(100% - 24px));margin:25px auto;color:#f4f4f4}.admin-orders-panel{padding:18px;margin-bottom:14px;background:linear-gradient(145deg,#0b0c18,#14182a);border:1px solid rgba(255,215,0,.4);border-radius:15px}.admin-orders h1,.admin-orders h2{color:#ffd700}.admin-orders a{color:#00e5d4}.admin-orders form.filters{display:flex;gap:8px;flex-wrap:wrap}.admin-orders input,.admin-orders select{min-height:38px;padding:6px 10px;color:#fff;background:#101522;border:1px solid #52606f;border-radius:8px}.admin-order{padding:14px 0;border-top:1px solid rgba(0,229,212,.2)}.admin-order:first-child{border:0}.admin-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:15px 0}.admin-summary-card{padding:13px;border:1px solid rgba(0,229,212,.3);border-radius:10px;background:#101522;display:flex;flex-direction:column;gap:7px}.admin-summary-card span{color:#aeb5c4;font-size:.88rem}.admin-summary-card strong{font-size:1.45rem;color:#ffd700}.admin-status-list{display:flex;gap:10px;flex-wrap:wrap}.admin-status-list span{padding:7px 10px;border:1px solid rgba(255,215,0,.25);border-radius:8px}.admin-order-head{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}.muted{color:#aeb5c4}.admin-btn{display:inline-block;padding:8px 13px;border:1px solid #00e5d4;border-radius:18px;text-decoration:none;background:transparent;color:#00e5d4!important}
</style>
<div class="admin-orders">
 <section class="admin-orders-panel">
  <p class="muted">{{ $organization->name }} · Администратор</p>
  <h1>Заказы организации</h1>
  <p><a href="{{ route('employee.organization.menu.index', $organization) }}">← Управление меню</a> · <a href="{{ route('employee.organization.admin.orders.export', array_merge(
      ['organization' => $organization->id],
      request()->query(),
      ['date_from' => $dateFrom?->format('Y-m-d'), 'date_to' => $dateTo?->format('Y-m-d')]
  )) }}">Экспорт CSV</a></p>
  <form class="filters" method="GET">
   <select name="period" onchange="this.form.submit()">
    <option value="day" @selected($period === 'day')>Сегодня</option>
    <option value="yesterday" @selected($period === 'yesterday')>Вчера</option>
    <option value="last7" @selected($period === 'last7')>Последние 7 дней</option>
    <option value="last30" @selected($period === 'last30')>Последние 30 дней</option>
    <option value="week" @selected($period === 'week')>Текущая неделя</option>
    <option value="month" @selected($period === 'month')>Текущий месяц</option>
    <option value="year" @selected($period === 'year')>Текущий год</option>
    <option value="all" @selected($period === 'all')>За всё время</option>
    <option value="custom" @selected($period === 'custom')>Выбрать период</option>
   </select>
   @if($period === 'custom')
    <label>С <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
    <label>По <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
    <button class="admin-btn" type="submit">Применить период</button>
   @endif
   <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Номер, имя или телефон">
   <select name="status"><option value="">Все статусы</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
   <select name="payment_status"><option value="">Любая оплата</option>@foreach($paymentLabels as $key=>$label)<option value="{{ $key }}" @selected(($filters['payment_status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
   <select name="fulfillment_type">
    <option value="">Любой способ получения</option>
    <option value="delivery" @selected(($filters['fulfillment_type'] ?? '') === 'delivery')>Доставка</option>
    <option value="pickup" @selected(($filters['fulfillment_type'] ?? '') === 'pickup')>Самовывоз</option>
    <option value="dine_in" @selected(($filters['fulfillment_type'] ?? '') === 'dine_in')>В заведении</option>
   </select>
   <select name="payment_method">
    <option value="">Любой способ оплаты</option>
    <option value="online" @selected(($filters['payment_method'] ?? '') === 'online')>Онлайн</option>
    <option value="cash_on_receipt" @selected(($filters['payment_method'] ?? '') === 'cash_on_receipt')>Наличные</option>
    <option value="card_on_receipt" @selected(($filters['payment_method'] ?? '') === 'card_on_receipt')>Картой при получении</option>
   </select>
   <button class="admin-btn" type="submit">Фильтровать</button>
   <a class="admin-btn" href="{{ route('employee.organization.admin.orders.index', $organization) }}">Сбросить</a>
  </form>
 </section>
 <section class="admin-orders-panel">
  <h2>Сводка за выбранный период</h2>
  <p class="muted">
   @if($dateFrom){{ $dateFrom->format('d.m.Y') }}@elseС начала работы@endif
   — @if($dateTo){{ $dateTo->format('d.m.Y') }}@elseпо настоящее время@endif
  </p>
  <div class="admin-summary-grid">
   <div class="admin-summary-card"><span>Всего заказов</span><strong>{{ $summary['count'] }}</strong></div>
   <div class="admin-summary-card"><span>Оплачено заказов</span><strong>{{ $summary['paid_count'] }}</strong></div>
   <div class="admin-summary-card"><span>Ожидают оплаты</span><strong>{{ $summary['pending_count'] }}</strong></div>
   <div class="admin-summary-card"><span>Отменено</span><strong>{{ $summary['cancelled_count'] }}</strong></div>
  </div>
  <h3>Денежные итоги</h3>
  @forelse($summary['amounts'] as $amount)
   <div class="admin-order">
    <strong>{{ $amount->currency }}</strong>
    <div>Количество: <strong>{{ $amount->orders_count }}</strong></div>
    <div>Общая сумма: <strong>{{ number_format((float)$amount->total_amount,2,',',' ') }}</strong></div>
    <div>Средний чек: <strong>{{ number_format((float)$amount->average_amount,2,',',' ') }}</strong></div>
    <div>Фактически оплачено: <strong>{{ number_format((float)$amount->paid_amount,2,',',' ') }}</strong></div>
    <div>Ожидает оплаты: <strong>{{ number_format((float)$amount->pending_amount,2,',',' ') }}</strong></div>
   </div>
  @empty
   <p class="muted">За выбранный период заказов нет.</p>
  @endforelse
  <h3>Способы получения</h3>
  <div class="admin-status-list">
   @foreach($summary['fulfillment'] as $row)
    <span>{{ $fulfillmentLabels[$row->fulfillment_type] ?? $row->fulfillment_type }}: <strong>{{ $row->orders_count }}</strong></span>
   @endforeach
  </div>
  <h3>Способы оплаты</h3>
  <div class="admin-status-list">
   @foreach($summary['payment_methods'] as $row)
    <span>{{ $paymentMethodLabels[$row->payment_method] ?? $row->payment_method }}: <strong>{{ $row->orders_count }}</strong></span>
   @endforeach
  </div>
  <h3>Распределение по статусам</h3>
  <div class="admin-status-list">
   @foreach($summary['statuses'] as $statusRow)
    <span>{{ $statusLabels[$statusRow->status] ?? $statusRow->status }}: <strong>{{ $statusRow->orders_count }}</strong></span>
   @endforeach
  </div>
 </section>
 <section class="admin-orders-panel">
  <h2>Список заказов</h2>
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
