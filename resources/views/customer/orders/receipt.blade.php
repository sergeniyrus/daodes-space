<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Электронный чек заказа</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px 12px; background: #f1f1f1; color: #171717; font-family: Arial, sans-serif; }
        .receipt { width: 100%; max-width: 560px; margin: 0 auto; padding: 28px; background: #fff; }
        .center { text-align: center; }
        .muted { color: #666; font-size: 13px; }
        .title { margin: 8px 0; font-size: 22px; font-weight: 700; }
        .rule { border: 0; border-top: 1px dashed #999; margin: 18px 0; }
        .row { display: flex; justify-content: space-between; gap: 16px; margin: 9px 0; }
        .row span:last-child { text-align: right; }
        .item { margin: 12px 0; }
        .item-detail { display: flex; justify-content: space-between; gap: 12px; margin-top: 4px; }
        .total { font-size: 19px; font-weight: 700; }
        .toolbar { max-width: 560px; margin: 0 auto 12px; text-align: center; }
        .toolbar button { border: 1px solid #087c75; border-radius: 6px; padding: 10px 18px; background: #fff; color: #087c75; font-weight: 700; cursor: pointer; }
        @media print {
            @page { size: A4; margin: 12mm; }
            body { padding: 0; background: #fff; }
            .receipt { max-width: none; padding: 0; }
            .toolbar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Распечатать чек</button>
    </div>
    <main class="receipt">
        <header class="center">
            <div class="title">{{ $order->organization->name }}</div>
            <div class="muted">Электронный чек онлайн-заказа</div>
            <h1>Заказ №{{ $order->order_number }}</h1>
            <div>{{ $order->created_at?->timezone($order->organization?->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') ?? '—' }}</div>
        </header>
        <hr class="rule">
        <div class="row"><span>Клиент</span><strong>{{ $order->customer_name }}</strong></div>
        <div class="row"><span>Телефон</span><span>{{ $order->customer_phone }}</span></div>
        <div class="row">
            <span>Получение</span>
            <strong>{{ ['delivery' => 'Доставка', 'pickup' => 'Самовывоз', 'dine_in' => 'В заведении'][$order->fulfillment_type] ?? $order->fulfillment_type }}</strong>
        </div>
        @if($order->fulfillment_type === 'pickup')
            <div style="margin-top: 10px;">
                <strong>Адрес самовывоза:</strong> {{ $order->pickup_address_snapshot ?: ($order->organization?->orderSettings?->pickup_address ?? 'Уточните адрес у заведения') }}
            </div>
        @endif
        @if($order->fulfillment_type === 'delivery')
            <div style="margin-top: 10px;">
                <strong>Адрес:</strong> {{ $order->delivery_address['address_line'] ?? 'Не указан' }}
                @if(!empty($order->delivery_address['entrance'])) , подъезд {{ $order->delivery_address['entrance'] }} @endif
                @if(!empty($order->delivery_address['floor'])) , этаж {{ $order->delivery_address['floor'] }} @endif
                @if(!empty($order->delivery_address['apartment'])) , кв. {{ $order->delivery_address['apartment'] }} @endif
            </div>
        @endif
        <hr class="rule">
        <h2>Состав заказа</h2>
        @foreach($order->items as $item)
            <div class="item">
                <strong>{{ $item->item_name }}</strong>
                <div class="item-detail">
                    <span>{{ $item->quantity }} × {{ number_format((float) $item->unit_price, 2, ',', ' ') }}</span>
                    <strong>{{ number_format((float) $item->line_total, 2, ',', ' ') }} {{ $order->currency }}</strong>
                </div>
            </div>
        @endforeach
        <hr class="rule">
        <div class="row"><span>Товары</span><span>{{ number_format((float) $order->subtotal, 2, ',', ' ') }} {{ $order->currency }}</span></div>
        @if((float) $order->delivery_fee > 0)
            <div class="row"><span>Доставка</span><span>{{ number_format((float) $order->delivery_fee, 2, ',', ' ') }} {{ $order->currency }}</span></div>
        @endif
        @if((float) $order->discount_total > 0)
            <div class="row"><span>Скидка</span><span>−{{ number_format((float) $order->discount_total, 2, ',', ' ') }} {{ $order->currency }}</span></div>
        @endif
        <div class="row total"><span>ИТОГО</span><span>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</span></div>
        <hr class="rule">
        <div class="row">
            <span>Способ оплаты</span>
            <span>{{ ['online' => 'Онлайн', 'cash_on_receipt' => 'Наличные при получении', 'card_on_receipt' => 'Картой при получении', 'guest_account' => 'На счёт гостя — расчёт при выходе'][$order->payment_method] ?? $order->payment_method }}</span>
        </div>
        <div class="row">
            <span>Статус оплаты</span>
            <strong>{{ ['pending' => ($order->payment_method === 'guest_account' ? 'Ожидает расчёта на кассе при выходе' : 'Ожидает оплаты'), 'succeeded' => 'Оплачен', 'failed' => 'Ошибка оплаты', 'cancelled' => 'Отменён', 'refunded' => 'Возвращён'][$order->payment_status] ?? $order->payment_status }}</strong>
        </div>
        @if($order->guest_card_identifier)
            <div class="row"><span>Браслет / карта сотрудника</span><strong>{{ $order->guest_card_identifier }}</strong></div>
        @endif
        @if($order->customer_comment)
            <hr class="rule">
            <div><strong>Комментарий:</strong> {{ $order->customer_comment }}</div>
        @endif
        <hr class="rule">
        <div class="center muted">Спасибо за заказ!</div>
    </main>
</body>
</html>
