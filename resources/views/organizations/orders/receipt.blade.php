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
        'card_on_receipt' => 'Картой при получении',
    ];
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Чек №{{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #ececec; color: #111; font-family: Arial, sans-serif; }
        .receipt-toolbar {
            display: flex; justify-content: center; gap: 10px; padding: 14px;
        }
        .receipt-toolbar button, .receipt-toolbar a {
            border: 1px solid #0a8f87; border-radius: 7px; padding: 9px 14px;
            background: #fff; color: #087c75; font-weight: 700; text-decoration: none; cursor: pointer;
        }
        .receipt {
            width: {{ $format === 'a4' ? '186mm' : '74mm' }};
            min-height: 40mm; margin: 0 auto 24px; padding: {{ $format === 'a4' ? '8mm' : '2mm' }};
            background: #fff; font-size: {{ $format === 'a4' ? '11pt' : '9pt' }};
            line-height: 1.35; overflow-wrap: anywhere;
        }
        .receipt--a4 { min-height: 260mm; }
        .receipt__center { text-align: center; }
        .receipt__organization { font-size: {{ $format === 'a4' ? '20pt' : '13pt' }}; font-weight: 800; }
        .receipt__heading { font-size: {{ $format === 'a4' ? '16pt' : '11pt' }}; font-weight: 700; margin-top: 5mm; }
        .receipt__muted { color: #444; font-size: .9em; }
        .receipt__rule { border: 0; border-top: 1px dashed #555; margin: 3mm 0; }
        .receipt__row { display: flex; justify-content: space-between; gap: 8px; margin: 1.5mm 0; }
        .receipt__row span:last-child { text-align: right; white-space: nowrap; }
        .receipt__item { margin: 2.5mm 0; }
        .receipt__item-name { font-weight: 700; }
        .receipt__item-detail { display: flex; justify-content: space-between; gap: 8px; }
        .receipt__total { font-size: 1.2em; font-weight: 800; }
        .receipt__qr { margin: 5mm auto 2mm; text-align: center; }
        .receipt__qr canvas, .receipt__qr img { display: block; margin: 0 auto; max-width: 34mm; }
        .receipt__qr-label { margin-top: 2mm; font-size: .8em; }
        .receipt__footer { margin-top: 5mm; text-align: center; font-size: .85em; }
        @page {
            size: {{ $format === 'a4' ? 'A4' : '80mm auto' }};
            margin: {{ $format === 'a4' ? '12mm' : '3mm' }};
        }
        @media print {
            html, body { width: auto; margin: 0; padding: 0; background: #fff; }
            .receipt-toolbar { display: none !important; }
            .receipt { width: 100%; min-height: 0; margin: 0; padding: 0; }
            .receipt--a4 { min-height: 0; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="receipt-toolbar">
        <button type="button" onclick="window.print()">Печать</button>
        <a href="{{ route('organizations.orders.show', [$organization, $order]) }}">Вернуться к заказу</a>
    </div>

    <main
        class="receipt {{ $format === 'a4' ? 'receipt--a4' : 'receipt--80mm' }}"
        data-receipt-format="{{ $format }}"
    >
        <header class="receipt__center">
            <div class="receipt__organization">{{ $organization->name }}</div>
            <div class="receipt__muted">Заказ онлайн</div>
            <div class="receipt__heading">ЧЕК №{{ $order->order_number }}</div>
            <div>{{ $order->created_at?->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') ?? '—' }}</div>
        </header>

        <hr class="receipt__rule">

        <div class="receipt__row">
            <span>Статус</span>
            <strong>{{ $statusLabels[$order->status] ?? $order->status }}</strong>
        </div>
        <div class="receipt__row">
            <span>Получение</span>
            <strong>{{ $fulfillmentLabels[$order->fulfillment_type] ?? $order->fulfillment_type }}</strong>
        </div>
        @if($order->requested_at)
            <div class="receipt__row">
                <span>Время</span>
                <strong>{{ $order->requested_at->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') }}</strong>
            </div>
        @endif
        @if($order->table_number)
            <div class="receipt__row">
                <span>Столик</span>
                <strong>{{ $order->table_number }}</strong>
            </div>
        @endif
        @if($order->guest_card_identifier)
            <div class="receipt__row">
                <span>Браслет / карта сотрудника</span>
                <strong>{{ $order->guest_card_identifier }}</strong>
            </div>
        @endif
        <div>Клиент: <strong>{{ $order->customer_name }}</strong></div>
        <div>Телефон: {{ $order->customer_phone }}</div>

        @if($order->fulfillment_type === 'pickup')
            <div style="margin-top: 2mm;">
                Адрес самовывоза: <strong>{{ $order->pickup_address_snapshot ?: ($organization->orderSettings?->pickup_address ?? 'Не указан') }}</strong>
            </div>
        @endif
        @if($order->fulfillment_type === 'delivery')
            <div style="margin-top: 2mm;">
                Адрес: {{ $order->delivery_address['address_line'] ?? 'Не указан' }}
                @if(!empty($order->delivery_address['entrance'])) , подъезд {{ $order->delivery_address['entrance'] }} @endif
                @if(!empty($order->delivery_address['floor'])) , этаж {{ $order->delivery_address['floor'] }} @endif
                @if(!empty($order->delivery_address['apartment'])) , кв. {{ $order->delivery_address['apartment'] }} @endif
            </div>
        @endif

        <hr class="receipt__rule">
        <div class="receipt__heading" style="margin-top: 0;">Состав заказа</div>

        @foreach($order->items as $item)
            <div class="receipt__item">
                <div class="receipt__item-name">{{ $item->item_name }}</div>
                <div class="receipt__item-detail">
                    <span>{{ $item->quantity }} × {{ number_format((float) $item->unit_price, 2, ',', ' ') }}</span>
                    <strong>{{ number_format((float) $item->line_total, 2, ',', ' ') }} {{ $order->currency }}</strong>
                </div>
                @if($item->options)
                    <div class="receipt__muted">
                        @foreach($item->options as $option)
                            {{ is_scalar($option) ? $option : json_encode($option, JSON_UNESCAPED_UNICODE) }}@if(!$loop->last), @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        <hr class="receipt__rule">
        <div class="receipt__row">
            <span>Товары</span>
            <span>{{ number_format((float) $order->subtotal, 2, ',', ' ') }} {{ $order->currency }}</span>
        </div>
        @if((float) $order->delivery_fee > 0)
            <div class="receipt__row">
                <span>Доставка</span>
                <span>{{ number_format((float) $order->delivery_fee, 2, ',', ' ') }} {{ $order->currency }}</span>
            </div>
        @endif
        @if((float) $order->discount_total > 0)
            <div class="receipt__row">
                <span>Скидка</span>
                <span>−{{ number_format((float) $order->discount_total, 2, ',', ' ') }} {{ $order->currency }}</span>
            </div>
        @endif
        <div class="receipt__row receipt__total">
            <span>ИТОГО</span>
            <span>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</span>
        </div>

        <hr class="receipt__rule">
        <div class="receipt__row">
            <span>Оплата</span>
            <span>{{ $order->payment_method === 'guest_account' ? 'На счёт гостя — расчёт при выходе' : ($methodLabels[$order->payment_method] ?? $order->payment_method) }}</span>
        </div>
        <div class="receipt__row">
            <span>Статус оплаты</span>
            <strong>{{ $order->payment_method === 'guest_account' && $order->payment_status === 'pending' ? 'Ожидает расчёта на кассе при выходе' : ($paymentLabels[$order->payment_status] ?? $order->payment_status) }}</strong>
        </div>

        @if($order->customer_comment)
            <hr class="receipt__rule">
            <div><strong>Комментарий:</strong> {{ $order->customer_comment }}</div>
        @endif

        <div class="receipt__qr">
            <div id="order-qr" data-qr-value="{{ $qrValue }}"></div>
            <div class="receipt__qr-label">QR-код карточки заказа</div>
            <div class="receipt__muted">{{ $order->order_number }}</div>
        </div>

        <footer class="receipt__footer">
            Спасибо за заказ!
        </footer>
    </main>

    <script src="{{ asset('js/vendor/qrcode.min.js') }}"></script>
    <script>
        (function () {
            var qrElement = document.getElementById('order-qr');
            if (!qrElement || typeof QRCode === 'undefined') {
                return;
            }

            var qrValue = qrElement.getAttribute('data-qr-value');
            var qrSize = document.querySelector('.receipt--a4') ? 160 : 112;

            new QRCode(qrElement, {
                text: qrValue,
                width: qrSize,
                height: qrSize,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        })();
    </script>
</body>
</html>
