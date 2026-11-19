@extends('organizations.layouts.management')

@section('title_page') Заказы — {{ $organization->name }} @endsection

@section('management-content')
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
.admin-orders-page{--gold:#ffd700;--cyan:#00e5d4;--muted:#aeb5c4;--line:rgba(0,229,212,.2);width:100%;max-width:none;margin:0;color:#f4f5fa}
.admin-orders-page *{box-sizing:border-box}
.admin-orders-shell{padding:clamp(16px,2.5vw,28px);background:radial-gradient(circle at 100% 0,rgba(255,215,0,.075),transparent 30%),radial-gradient(circle at 0 100%,rgba(0,229,212,.045),transparent 32%),linear-gradient(145deg,#0b0c18,#111626 55%,#0b0c18);border:1px solid rgba(255,215,0,.42);border-radius:22px;box-shadow:0 16px 48px rgba(0,0,0,.38);margin-bottom:16px}
.admin-orders-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid rgba(255,215,0,.2)}
.admin-orders-eyebrow{color:var(--cyan);font-size:.82rem;letter-spacing:.08em;text-transform:uppercase;margin:0 0 7px}
.admin-orders-page h1,.admin-orders-page h2,.admin-orders-page h3{margin-top:0;color:var(--gold)}
.admin-orders-page h1{font-size:clamp(1.45rem,3vw,2rem);margin-bottom:7px}
.admin-orders-page h2{font-size:1.25rem;margin-bottom:8px}
.admin-orders-page h3{font-size:1rem;margin:20px 0 10px}
.admin-orders-subtitle,.admin-orders-muted{color:var(--muted)}
.admin-orders-subtitle{margin:0}
.admin-orders-actions{display:flex;gap:9px;flex-wrap:wrap}
.admin-orders-link,.admin-orders-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:8px 14px;border:1px solid var(--cyan);border-radius:12px;background:rgba(0,229,212,.045);color:var(--cyan)!important;text-decoration:none;font-weight:600;cursor:pointer;transition:.2s}
.admin-orders-link:hover,.admin-orders-button:hover{background:var(--cyan);color:#071316!important;box-shadow:0 0 16px rgba(0,229,212,.18)}
.admin-orders-link.gold{border-color:var(--gold);color:var(--gold)!important;background:rgba(255,215,0,.045)}
.admin-orders-link.gold:hover{background:var(--gold);color:#171506!important}
.admin-orders-filters{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:11px;align-items:end}
.admin-orders-field{grid-column:span 3;min-width:0}
.admin-orders-field.search{grid-column:span 4}
.admin-orders-field.period{grid-column:span 3}
.admin-orders-field label{display:block;margin-bottom:6px;color:var(--muted);font-size:.82rem}
.admin-orders-page input,.admin-orders-page select{width:100%;min-width:0;min-height:42px;padding:8px 11px;background:#0b1220;color:#f5f6fa;border:1px solid #39495a;border-radius:10px;outline:none}
.admin-orders-page input:focus,.admin-orders-page select:focus{border-color:var(--cyan);box-shadow:0 0 0 3px rgba(0,229,212,.1)}
.admin-orders-page select option{background:#101522;color:#fff}
.admin-orders-filter-actions{display:flex;gap:8px;flex-wrap:wrap;grid-column:span 12;margin-top:2px}
.admin-orders-period-dates{grid-column:span 12;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.admin-orders-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px}
.admin-orders-stat{position:relative;overflow:hidden;padding:17px;border:1px solid rgba(0,229,212,.24);border-radius:15px;background:linear-gradient(145deg,rgba(20,31,47,.96),rgba(12,18,31,.96))}
.admin-orders-stat:after{content:"";position:absolute;width:90px;height:90px;right:-42px;top:-42px;border-radius:50%;background:rgba(0,229,212,.055)}
.admin-orders-stat.gold{border-color:rgba(255,215,0,.28)}
.admin-orders-stat small{display:block;color:var(--muted);font-size:.84rem;margin-bottom:9px}
.admin-orders-stat strong{display:block;color:var(--gold);font-size:clamp(1.45rem,3vw,2rem);line-height:1.15;font-variant-numeric:tabular-nums}
.admin-orders-stat.cyan strong{color:var(--cyan)}
.admin-orders-period{display:inline-flex;padding:6px 10px;border-radius:8px;background:rgba(0,229,212,.07);color:var(--cyan);font-size:.84rem}
.admin-orders-money-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:14px}
.admin-orders-money-card{padding:16px;border:1px solid rgba(255,215,0,.22);border-radius:14px;background:rgba(8,13,24,.56)}
.admin-orders-money-card h3{margin:0 0 13px;color:var(--gold);font-size:1.1rem}
.admin-orders-money-line{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid rgba(255,255,255,.065);font-size:.9rem}
.admin-orders-money-line span{color:var(--muted)}
.admin-orders-money-line strong{text-align:right;color:#f4f5fa;font-variant-numeric:tabular-nums}
.admin-orders-breakdowns{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}
.admin-orders-breakdown{padding:15px;border:1px solid var(--line);border-radius:14px;background:rgba(8,13,24,.48);min-width:0}
.admin-orders-breakdown h3{margin:0 0 12px}
.admin-orders-chip-list{display:flex;flex-wrap:wrap;gap:8px}
.admin-orders-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border:1px solid rgba(255,215,0,.2);border-radius:9px;background:rgba(255,255,255,.025);font-size:.83rem}
.admin-orders-chip strong{color:var(--gold)}
.admin-orders-list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.admin-orders-list-head h2{margin:0}
.admin-orders-count{color:var(--muted);font-size:.88rem}
.admin-order-card{padding:17px 0;border-top:1px solid var(--line)}
.admin-order-card:first-of-type{border-top:0}
.admin-order-card-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.admin-order-number{color:var(--gold);font-size:1.08rem;font-weight:700}
.admin-order-badges{display:flex;gap:7px;flex-wrap:wrap}
.admin-order-badge{display:inline-flex;padding:5px 9px;border:1px solid rgba(0,229,212,.25);border-radius:20px;color:var(--cyan);font-size:.78rem}
.admin-order-badge.payment{border-color:rgba(255,215,0,.28);color:var(--gold)}
.admin-order-customer{margin:9px 0 5px;font-weight:600}
.admin-order-meta{display:flex;gap:8px 15px;flex-wrap:wrap;color:var(--muted);font-size:.85rem}
.admin-order-items{margin:10px 0;color:#d5d9e3;font-size:.9rem;line-height:1.55}
.admin-order-bottom{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.admin-order-total{color:var(--gold);font-size:1.1rem;font-weight:700;font-variant-numeric:tabular-nums}
.admin-orders-empty{padding:30px 15px;text-align:center;color:var(--muted);border:1px dashed rgba(0,229,212,.25);border-radius:13px}
.product-analytics-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:16px}
.product-analytics-card{min-width:0;padding:17px;border:1px solid rgba(0,229,212,.24);border-radius:15px;background:linear-gradient(145deg,rgba(17,29,44,.92),rgba(9,15,27,.92))}
.product-analytics-card.least{border-color:rgba(255,215,0,.25)}
.product-analytics-card h3{margin:0 0 5px;color:var(--gold)}
.product-analytics-hint{margin:0 0 13px;color:var(--muted);font-size:.82rem;line-height:1.45}
.product-analytics-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-top:1px solid rgba(255,255,255,.07)}
.product-analytics-name{min-width:0;color:#f2f4f8;font-weight:650;overflow-wrap:anywhere}
.product-analytics-meta{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:5px;color:var(--muted);font-size:.78rem}
.product-analytics-quantity{flex:0 0 auto;text-align:right;color:var(--cyan);font-weight:750;font-variant-numeric:tabular-nums}
.product-analytics-card.least .product-analytics-quantity{color:var(--gold)}
.product-analytics-quantity small{display:block;color:var(--muted);font-size:.72rem;font-weight:500}
.product-analytics-zero{color:#ffb86b}
.product-analytics-state{padding:3px 7px;border:1px solid rgba(255,255,255,.12);border-radius:20px;font-size:.7rem}
@media(max-width:700px){.product-analytics-grid{grid-template-columns:1fr}.product-analytics-card{padding:14px}}

.admin-orders-pagination{margin-top:18px}
.admin-orders-pagination nav{display:flex;justify-content:center}
.admin-orders-page .pagination{flex-wrap:wrap;gap:5px}
.admin-orders-page .page-link{background:#101827;color:var(--cyan);border-color:#344456}
.admin-orders-page .page-item.active .page-link{background:var(--cyan);border-color:var(--cyan);color:#081416}
@media(max-width:900px){.admin-orders-field,.admin-orders-field.search,.admin-orders-field.period{grid-column:span 6}.admin-orders-breakdowns{grid-template-columns:1fr 1fr}.admin-orders-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.admin-orders-page{width:calc(100% - 16px);margin:14px auto 30px}.admin-orders-shell{padding:15px;border-radius:16px}.admin-orders-heading{flex-direction:column}.admin-orders-actions{width:100%}.admin-orders-actions>*{flex:1}.admin-orders-field,.admin-orders-field.search,.admin-orders-field.period{grid-column:span 12}.admin-orders-period-dates{grid-template-columns:1fr}.admin-orders-summary-grid{gap:8px}.admin-orders-stat{padding:13px}.admin-orders-stat small{font-size:.76rem}.admin-orders-breakdowns{grid-template-columns:1fr}.admin-order-bottom>*{width:100%}.admin-order-bottom .admin-orders-link{width:100%}}
</style>
<style>@media(max-width:560px){.admin-orders-page{width:100%;margin:0}}</style>

<div class="admin-orders-page">
    <section class="admin-orders-shell">
        <header class="admin-orders-heading">
            <div>
                <p class="admin-orders-eyebrow">АНАЛИТИКА ЗАКАЗОВ</p>
                <h1>Заказы организации</h1>
                <p class="admin-orders-subtitle">Контроль заказов, оплат и показателей работы</p>
            </div>
            <div class="admin-orders-actions">
                <a class="admin-orders-link" href="{{ route('employee.organization.menu.index', $organization) }}">← Управление меню</a>
                <a class="admin-orders-link gold" href="{{ route('employee.organization.admin.orders.export', array_merge(
                    ['organization' => $organization->id],
                    request()->query(),
                    ['date_from' => $dateFrom?->format('Y-m-d'), 'date_to' => $dateTo?->format('Y-m-d')]
                )) }}">⇩ Экспорт CSV</a>
            </div>
        </header>

        <form class="admin-orders-filters" method="GET">
            <div class="admin-orders-field period">
                <label for="admin-period">Период аналитики</label>
                <select id="admin-period" name="period" onchange="this.form.submit()">
                    <option value="day" @selected($period === 'day')>Сегодня</option>
                    <option value="yesterday" @selected($period === 'yesterday')>Вчера</option>
                    <option value="last7" @selected($period === 'last7')>Последние 7 дней</option>
                    <option value="last30" @selected($period === 'last30')>Последние 30 дней</option>
                    <option value="week" @selected($period === 'week')>Текущая неделя</option>
                    <option value="month" @selected($period === 'month')>Текущий месяц</option>
                    <option value="year" @selected($period === 'year')>Текущий год</option>
                    <option value="all" @selected($period === 'all')>За всё время</option>
                    <option value="custom" @selected($period === 'custom')>Произвольный период</option>
                </select>
            </div>

            @if($period === 'custom')
                <div class="admin-orders-period-dates">
                    <div><label for="admin-date-from">Начало периода</label><input id="admin-date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                    <div><label for="admin-date-to">Конец периода</label><input id="admin-date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
                </div>
            @endif

            <div class="admin-orders-field search">
                <label for="admin-search">Поиск заказа</label>
                <input id="admin-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Номер, имя или телефон">
            </div>
            <div class="admin-orders-field">
                <label for="admin-status">Статус заказа</label>
                <select id="admin-status" name="status"><option value="">Все статусы</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
            </div>
            <div class="admin-orders-field">
                <label for="admin-payment-status">Состояние оплаты</label>
                <select id="admin-payment-status" name="payment_status"><option value="">Любое</option>@foreach($paymentLabels as $key=>$label)<option value="{{ $key }}" @selected(($filters['payment_status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
            </div>
            <div class="admin-orders-field">
                <label for="admin-fulfillment">Способ получения</label>
                <select id="admin-fulfillment" name="fulfillment_type">
                    <option value="">Любой способ</option>
                    <option value="delivery" @selected(($filters['fulfillment_type'] ?? '') === 'delivery')>Доставка</option>
                    <option value="pickup" @selected(($filters['fulfillment_type'] ?? '') === 'pickup')>Самовывоз</option>
                    <option value="dine_in" @selected(($filters['fulfillment_type'] ?? '') === 'dine_in')>В заведении</option>
                </select>
            </div>
            <div class="admin-orders-field">
                <label for="admin-payment-method">Способ оплаты</label>
                <select id="admin-payment-method" name="payment_method">
                    <option value="">Любой способ</option>
                    <option value="online" @selected(($filters['payment_method'] ?? '') === 'online')>Онлайн</option>
                    <option value="cash_on_receipt" @selected(($filters['payment_method'] ?? '') === 'cash_on_receipt')>Наличные</option>
                    <option value="card_on_receipt" @selected(($filters['payment_method'] ?? '') === 'card_on_receipt')>Картой при получении</option>
                </select>
            </div>
            <div class="admin-orders-filter-actions">
                <button class="admin-orders-button" type="submit">Применить фильтры</button>
                <a class="admin-orders-link" href="{{ route('employee.organization.admin.orders.index', $organization) }}">Сбросить</a>
            </div>
        </form>
    </section>

    <section class="admin-orders-shell">
        <div class="admin-orders-list-head">
            <div>
                <h2>Аналитика заказов</h2>
                <div class="admin-orders-period">
                    @if($dateFrom){{ $dateFrom->format('d.m.Y') }}@elseС начала работы@endif
                    — @if($dateTo){{ $dateTo->format('d.m.Y') }}@elseпо настоящее время@endif
                </div>
            </div>
        </div>

        <div class="admin-orders-summary-grid">
            <div class="admin-orders-stat"><small>Всего заказов</small><strong>{{ $summary['count'] }}</strong></div>
            <div class="admin-orders-stat cyan"><small>Оплачено заказов</small><strong>{{ $summary['paid_count'] }}</strong></div>
            <div class="admin-orders-stat gold"><small>Ожидают оплаты</small><strong>{{ $summary['pending_count'] }}</strong></div>
            <div class="admin-orders-stat"><small>Отменено</small><strong>{{ $summary['cancelled_count'] }}</strong></div>
        </div>

        <h3>Финансовые показатели</h3>
        <div class="admin-orders-money-grid">
            @forelse($summary['amounts'] as $amount)
                <div class="admin-orders-money-card">
                    <h3>{{ $amount->currency }}</h3>
                    <div class="admin-orders-money-line"><span>Заказов</span><strong>{{ $amount->orders_count }}</strong></div>
                    <div class="admin-orders-money-line"><span>Общая сумма</span><strong>{{ number_format((float)$amount->total_amount,2,',',' ') }}</strong></div>
                    <div class="admin-orders-money-line"><span>Средний чек</span><strong>{{ number_format((float)$amount->average_amount,2,',',' ') }}</strong></div>
                    <div class="admin-orders-money-line"><span>Фактически оплачено</span><strong>{{ number_format((float)$amount->paid_amount,2,',',' ') }}</strong></div>
                    <div class="admin-orders-money-line"><span>Ожидает оплаты</span><strong>{{ number_format((float)$amount->pending_amount,2,',',' ') }}</strong></div>
                </div>
            @empty
                <p class="admin-orders-muted">За выбранный период финансовых данных нет.</p>
            @endforelse
        </div>

        <div class="admin-orders-breakdowns">
            <div class="admin-orders-breakdown">
                <h3>Способы получения</h3>
                <div class="admin-orders-chip-list">
                    @forelse($summary['fulfillment'] as $row)
                        <span class="admin-orders-chip">{{ $fulfillmentLabels[$row->fulfillment_type] ?? $row->fulfillment_type }} <strong>{{ $row->orders_count }}</strong></span>
                    @empty <span class="admin-orders-muted">Нет данных</span> @endforelse
                </div>
            </div>
            <div class="admin-orders-breakdown">
                <h3>Способы оплаты</h3>
                <div class="admin-orders-chip-list">
                    @forelse($summary['payment_methods'] as $row)
                        <span class="admin-orders-chip">{{ $paymentMethodLabels[$row->payment_method] ?? $row->payment_method }} <strong>{{ $row->orders_count }}</strong></span>
                    @empty <span class="admin-orders-muted">Нет данных</span> @endforelse
                </div>
            </div>
            <div class="admin-orders-breakdown">
                <h3>Статусы заказов</h3>
                <div class="admin-orders-chip-list">
                    @forelse($summary['statuses'] as $statusRow)
                        <span class="admin-orders-chip">{{ $statusLabels[$statusRow->status] ?? $statusRow->status }} <strong>{{ $statusRow->orders_count }}</strong></span>
                    @empty <span class="admin-orders-muted">Нет данных</span> @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="admin-orders-shell">
        <div class="admin-orders-list-head">
            <div>
                <h2>Продажи товаров</h2>
                <p class="admin-orders-muted" style="margin:5px 0 0;">Рейтинг рассчитывается по оплаченным, неотменённым заказам за выбранный период. Учитываются также отключённые позиции меню.</p>
            </div>
            <span class="admin-orders-period">Топ-10 / Антирейтинг</span>
        </div>

        <div class="product-analytics-grid">
            <div class="product-analytics-card">
                <h3>🔥 Самые ходовые</h3>
                <p class="product-analytics-hint">Позиции с наибольшим количеством проданных единиц.</p>
                @forelse($bestSellingProducts as $product)
                    <div class="product-analytics-row">
                        <div style="min-width:0;">
                            <div class="product-analytics-name">{{ $product->name }}</div>
                            <div class="product-analytics-meta">
                                <span>{{ $product->orders_count }} заказ(ов)</span>
                                @if(!$product->is_active)
                                    <span class="product-analytics-state">Отключено в меню</span>
                                @endif
                            </div>
                        </div>
                        <div class="product-analytics-quantity">
                            {{ number_format((int) $product->sold_quantity, 0, ',', ' ') }}
                            <small>продано шт.</small>
                        </div>
                    </div>
                @empty
                    <div class="admin-orders-empty">В меню пока нет товаров.</div>
                @endforelse
            </div>

            <div class="product-analytics-card least">
                <h3>◷ Самые невостребованные</h3>
                <p class="product-analytics-hint">Позиции с минимальным количеством продаж. Товары без продаж также попадают в рейтинг.</p>
                @forelse($leastSellingProducts as $product)
                    <div class="product-analytics-row">
                        <div style="min-width:0;">
                            <div class="product-analytics-name">{{ $product->name }}</div>
                            <div class="product-analytics-meta">
                                <span>{{ $product->orders_count }} заказ(ов)</span>
                                @if(!$product->is_active)
                                    <span class="product-analytics-state">Отключено в меню</span>
                                @endif
                            </div>
                        </div>
                        <div class="product-analytics-quantity {{ (int) $product->sold_quantity === 0 ? 'product-analytics-zero' : '' }}">
                            {{ number_format((int) $product->sold_quantity, 0, ',', ' ') }}
                            <small>продано шт.</small>
                        </div>
                    </div>
                @empty
                    <div class="admin-orders-empty">В меню пока нет товаров.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="admin-orders-shell">
        <div class="admin-orders-list-head">
            <h2>Список заказов</h2>
            <span class="admin-orders-count">Найдено: {{ $orders->total() }}</span>
        </div>

        @forelse($orders as $order)
            <article class="admin-order-card">
                <div class="admin-order-card-head">
                    <span class="admin-order-number">Заказ №{{ $order->order_number }}</span>
                    <div class="admin-order-badges">
                        <span class="admin-order-badge">{{ $statusLabels[$order->status] ?? $order->status }}</span>
                        <span class="admin-order-badge payment">{{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}</span>
                    </div>
                </div>
                <div class="admin-order-customer">{{ $order->customer_name }} · {{ $order->customer_phone }}</div>
                <div class="admin-order-meta">
                    <span>◷ {{ $order->created_at?->format('d.m.Y H:i') }}</span>
                    <span>{{ $fulfillmentLabels[$order->fulfillment_type] ?? $order->fulfillment_type }}</span>
                    <span>{{ $paymentMethodLabels[$order->payment_method] ?? $order->payment_method }}</span>
                </div>
                <div class="admin-order-items">{{ $order->items->map(fn($item) => $item->item_name.' × '.$item->quantity)->implode(', ') }}</div>
                <div class="admin-order-bottom">
                    <strong class="admin-order-total">{{ number_format((float)$order->total,2,',',' ') }} {{ $order->currency }}</strong>
                    <a class="admin-orders-link" href="{{ route('employee.organization.admin.orders.show', [$organization,$order]) }}">Открыть заказ →</a>
                </div>
            </article>
        @empty
            <div class="admin-orders-empty">По выбранным условиям заказы не найдены.</div>
        @endforelse

        <div class="admin-orders-pagination">{{ $orders->links() }}</div>
    </section>
</div>
@endsection
