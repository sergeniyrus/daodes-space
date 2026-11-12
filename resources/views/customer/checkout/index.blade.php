@extends('template')

@section('title_page')
    Оформление заказа
@endsection

@section('main')
<style>
.customer-checkout-page {
    width: min(980px, calc(100% - 24px));
    margin: 28px auto 55px;
    color: #f4f4f4;
}
.customer-checkout-panel {
    padding: 22px;
    margin-bottom: 16px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .45);
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .35);
}
.customer-checkout-title { margin: 0 0 8px; color: #ffd700; }
.customer-checkout-muted { color: #aeb5c4; }
.customer-checkout-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr));
    gap: 13px;
}
.customer-checkout-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin: 13px 0;
    color: #d8dce8;
}
.customer-checkout-field input,
.customer-checkout-field select,
.customer-checkout-field textarea {
    box-sizing: border-box;
    width: 100%;
    min-height: 43px;
    padding: 9px 12px;
    color: #fff;
    background: #090c16;
    border: 1px solid rgba(0, 229, 212, .45);
    border-radius: 9px;
}
.customer-checkout-field textarea { min-height: 85px; resize: vertical; }
.customer-checkout-field input:focus,
.customer-checkout-field select:focus,
.customer-checkout-field textarea:focus {
    border-color: gold;
    outline: none;
}
.customer-checkout-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
    gap: 12px;
    margin-top: 12px;
}
.customer-checkout-options label {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 12px;
    color: #e8eaf0;
    background: rgba(0, 229, 212, .04);
    border: 1px solid rgba(0, 229, 212, .25);
    border-radius: 10px;
}
.customer-checkout-options input { accent-color: #00e5d4; }
.customer-checkout-line,
.customer-checkout-total {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    padding: 9px 0;
    border-bottom: 1px solid rgba(0, 229, 212, .16);
}
.customer-checkout-total {
    margin-top: 10px;
    color: #ffd700;
    border: 0;
    font-size: 1.18rem;
    font-weight: 700;
}
.customer-checkout-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 9px 20px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .06);
    border: 1px solid rgba(0, 229, 212, .65);
    border-radius: 23px;
    cursor: pointer;
    font-weight: 700;
}
.customer-checkout-button:hover { color: #081016; background: #00e5d4; }
.customer-checkout-error { color: #ff8585; font-size: .86rem; }
.customer-checkout-note {
    margin-top: 12px;
    color: #aeb5c4;
    font-size: .88rem;
    line-height: 1.5;
}
@media(max-width:600px) {
    .customer-checkout-panel { padding: 16px; }
}
</style>

<div class="customer-checkout-page">
    @if($errors->any())
        <div class="customer-checkout-panel">
            <div class="customer-checkout-error">
                Проверьте данные формы:
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="customer-checkout-panel">
        <h1 class="customer-checkout-title">Оформление заказа</h1>
        <div class="customer-checkout-muted">{{ $organization->name }}</div>

        @foreach($lines as $line)
            <div class="customer-checkout-line">
                <span>
                    {{ $line['item']->name }} × {{ $line['quantity'] }}
                </span>
                <strong>
                    {{ number_format((float) $line['line_total'], 2, ',', ' ') }}
                    {{ $settings->currency }}
                </strong>
            </div>
        @endforeach

        <div class="customer-checkout-line">
            <span>Товары</span>
            <span>{{ number_format((float) $subtotal, 2, ',', ' ') }} {{ $settings->currency }}</span>
        </div>
        <div class="customer-checkout-line">
            <span>Доставка (будет добавлена только при доставке)</span>
            <span>{{ number_format((float) $deliveryFee, 2, ',', ' ') }} {{ $settings->currency }}</span>
        </div>
        <div class="customer-checkout-total">
            <span>Итого при доставке</span>
            <span>{{ number_format((float) $deliveryTotal, 2, ',', ' ') }} {{ $settings->currency }}</span>
        </div>

        @if($minimumOrder !== null)
            <div class="customer-checkout-note">
                Минимальная сумма товаров: {{ number_format((float) $minimumOrder, 2, ',', ' ') }} {{ $settings->currency }}.
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('customer.checkout.store') }}">
        @csrf
        <input
            type="hidden"
            name="idempotency_key"
            value="{{ old('idempotency_key', $idempotencyKey) }}"
        >

        <div class="customer-checkout-panel">
            <h2 class="customer-checkout-title">Контактные данные</h2>

            <div class="customer-checkout-grid">
                <label class="customer-checkout-field">
                    Ваше имя
                    <input
                        type="text"
                        name="customer_name"
                        value="{{ old('customer_name', $customer->name) }}"
                        maxlength="100"
                        autocomplete="name"
                        required
                    >
                    @error('customer_name')
                        <span class="customer-checkout-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="customer-checkout-field">
                    Подтверждённый телефон
                    <input type="tel" value="{{ $customer->phone }}" readonly>
                    <span class="customer-checkout-muted">Телефон подтверждён по SMS.</span>
                </label>
            </div>

            <label class="customer-checkout-field">
                Комментарий к заказу
                <textarea name="customer_comment" maxlength="2000">{{ old('customer_comment') }}</textarea>
            </label>
        </div>

        <div class="customer-checkout-panel">
            <h2 class="customer-checkout-title">Способ получения</h2>

            <div class="customer-checkout-options">
                @if($settings->delivery_enabled)
                    <label>
                        <input
                            type="radio"
                            name="fulfillment_type"
                            value="delivery"
                            @checked(old('fulfillment_type', $settings->pickup_enabled ? 'pickup' : ($settings->dine_in_enabled ? 'dine_in' : 'delivery')) === 'delivery')
                        >
                        🚚 Доставка
                    </label>
                @endif

                @if($settings->pickup_enabled)
                    <label>
                        <input
                            type="radio"
                            name="fulfillment_type"
                            value="pickup"
                            @checked(old('fulfillment_type', $settings->pickup_enabled ? 'pickup' : ($settings->dine_in_enabled ? 'dine_in' : 'delivery')) === 'pickup')
                        >
                        🛍 Самовывоз
                    </label>
                @endif

                @if($settings->dine_in_enabled)
                    <label>
                        <input
                            type="radio"
                            name="fulfillment_type"
                            value="dine_in"
                            @checked(old('fulfillment_type', $settings->pickup_enabled ? 'pickup' : ($settings->dine_in_enabled ? 'dine_in' : 'delivery')) === 'dine_in')
                        >
                        🍽 В заведении
                    </label>
                @endif
            </div>
            @error('fulfillment_type')
                <div class="customer-checkout-error">{{ $message }}</div>
            @enderror

            <div class="customer-checkout-grid">
                <label class="customer-checkout-field">
                    Желаемое время (обязательно для самовывоза)
                    <input
                        type="datetime-local"
                        name="requested_at"
                        value="{{ old('requested_at') }}"
                        min="{{ now()->format('Y-m-d\TH:i') }}"
                    >
                    @if($settings->pickup_min_minutes !== null)
                        <span class="customer-checkout-muted">
                            Самовывоз не раньше чем через {{ $settings->pickup_min_minutes }} мин.
                        </span>
                    @endif
                    @error('requested_at')
                        <span class="customer-checkout-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="customer-checkout-field">
                    Номер столика (для заказа в заведении)
                    <input
                        type="text"
                        name="table_number"
                        value="{{ old('table_number') }}"
                        maxlength="30"
                        placeholder="Например, 7"
                    >
                </label>
            </div>
        </div>

        <div class="customer-checkout-panel">
            <h2 class="customer-checkout-title">Адрес доставки</h2>
            <div class="customer-checkout-note">
                Заполните адрес, если выбрали доставку. При самовывозе или заказе в заведении эти поля не используются.
            </div>

            <label class="customer-checkout-field">
                Адрес
                <input
                    type="text"
                    name="address_line"
                    value="{{ old('address_line') }}"
                    maxlength="500"
                    placeholder="Улица, дом, корпус"
                >
                @error('address_line')
                    <span class="customer-checkout-error">{{ $message }}</span>
                @enderror
            </label>

            <div class="customer-checkout-grid">
                <label class="customer-checkout-field">
                    Подъезд
                    <input type="text" name="entrance" value="{{ old('entrance') }}" maxlength="30">
                </label>
                <label class="customer-checkout-field">
                    Этаж
                    <input type="text" name="floor" value="{{ old('floor') }}" maxlength="30">
                </label>
                <label class="customer-checkout-field">
                    Квартира / офис
                    <input type="text" name="apartment" value="{{ old('apartment') }}" maxlength="30">
                </label>
                <label class="customer-checkout-field">
                    Домофон
                    <input type="text" name="intercom" value="{{ old('intercom') }}" maxlength="50">
                </label>
            </div>

            <label class="customer-checkout-field">
                Комментарий курьеру
                <textarea name="delivery_comment" maxlength="1000">{{ old('delivery_comment') }}</textarea>
            </label>
        </div>

        <div class="customer-checkout-panel">
            <h2 class="customer-checkout-title">Способ оплаты</h2>

            <div class="customer-checkout-options">
                @if($settings->cash_payment_enabled)
                    <label>
                        <input
                            type="radio"
                            name="payment_method"
                            value="cash_on_receipt"
                            @checked(old('payment_method', $settings->cash_payment_enabled ? 'cash_on_receipt' : 'card_on_receipt') === 'cash_on_receipt')
                        >
                        💵 Наличные при получении
                    </label>
                @endif

                @if($settings->card_payment_enabled)
                    <label>
                        <input
                            type="radio"
                            name="payment_method"
                            value="card_on_receipt"
                            @checked(old('payment_method', $settings->cash_payment_enabled ? 'cash_on_receipt' : 'card_on_receipt') === 'card_on_receipt')
                        >
                        💳 Терминал при получении
                    </label>
                @endif
            </div>

            @if($settings->online_payment_enabled)
                <div class="customer-checkout-note">
                    Онлайн-оплата пока не активна: платёжный провайдер ещё не подключён.
                    Заказ можно оформить с оплатой при получении.
                </div>
            @endif

            @error('payment_method')
                <div class="customer-checkout-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="customer-checkout-button">
            Подтвердить заказ
        </button>
        <a
            href="{{ route('customer.cart') }}"
            style="display: inline-block; margin-left: 12px; color: #00e5d4; text-decoration: none;"
        >
            Вернуться в корзину
        </a>
    </form>
</div>
@endsection
