@extends('customer.layouts.workspace')

@section('title_page')
    Оформление заказа
@endsection

@section('customer-content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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

.customer-checkout-address-map{height:240px;margin:10px 0;border:1px solid rgba(0,229,212,.4);border-radius:12px;overflow:hidden;background:#101522}
.customer-checkout-address-map-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px;color:#aeb5c4;font-size:.88rem}
.customer-checkout-address-map-tools button{min-height:36px;padding:7px 13px;color:#00e5d4;background:rgba(0,229,212,.07);border:1px solid rgba(0,229,212,.45);border-radius:20px;font-weight:700;cursor:pointer}
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
.customer-checkout-options label.is-unavailable { opacity: .42; cursor: not-allowed; }
.customer-checkout-options label.is-unavailable input { cursor: not-allowed; }
.customer-checkout-payment-options { display: flex; justify-content: center; flex-wrap: wrap; }
.customer-checkout-payment-options label { min-width: min(260px, 100%); }
.customer-checkout-address-details { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.customer-checkout-details { margin: 12px 0; color: #d8dce8; }
.customer-checkout-details summary { display: inline-flex; align-items: center; gap: 8px; color: #00e5d4; cursor: pointer; font-weight: 700; }
.customer-checkout-details summary::marker { color: #ffd700; }
.customer-checkout-details[open] summary { margin-bottom: 4px; }
.customer-checkout-details .customer-checkout-field { margin-top: 8px; }
.customer-checkout-line,
.customer-checkout-total {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    padding: 9px 0;
    border-bottom: 1px solid rgba(0, 229, 212, .16);
}
.customer-checkout-order-panel {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(ellipse at 92% 0%, rgba(0, 229, 212, .12), transparent 42%),
        radial-gradient(ellipse at 0% 100%, rgba(255, 215, 0, .08), transparent 38%),
        linear-gradient(145deg, #090b16, #171b2c 58%, #090b16);
}
.customer-checkout-order-panel::before {
    content: "";
    position: absolute;
    top: 0;
    left: 22px;
    right: 22px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #00e5d4 25%, #ffd700 75%, transparent);
    opacity: .9;
}
.customer-checkout-order-panel .customer-checkout-title {
    margin-bottom: 7px;
    color: #ffd700;
    font-size: clamp(1.35rem, 3vw, 1.75rem);
    letter-spacing: .035em;
    text-shadow: 0 2px 14px rgba(255, 215, 0, .18);
}
.customer-checkout-section-panel {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(ellipse at 94% 0%, rgba(0, 229, 212, .09), transparent 43%),
        radial-gradient(ellipse at 0% 100%, rgba(255, 215, 0, .055), transparent 40%),
        linear-gradient(145deg, #090b16, #171b2c 58%, #090b16);
    border-color: rgba(0, 229, 212, .32);
}
.customer-checkout-section-panel::before {
    content: "";
    position: absolute;
    top: 0;
    left: 22px;
    right: 22px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #00e5d4 28%, #ffd700 72%, transparent);
    opacity: .85;
}
.customer-checkout-section-panel .customer-checkout-title {
    position: relative;
    margin: 0 0 17px;
    padding-bottom: 11px;
    color: #ffd700;
    font-size: clamp(1.2rem, 2.7vw, 1.55rem);
    letter-spacing: .025em;
    text-shadow: 0 2px 13px rgba(255, 215, 0, .16);
    border-bottom: 1px solid rgba(0, 229, 212, .2);
}
.customer-checkout-section-panel .customer-checkout-options label {
    min-height: 52px;
    box-sizing: border-box;
    padding: 14px 16px;
    background: linear-gradient(110deg, rgba(0, 229, 212, .075), rgba(255, 255, 255, .025));
    border: 1px solid rgba(0, 229, 212, .28);
    border-radius: 11px;
    transition: border-color .18s ease, background .18s ease, box-shadow .18s ease;
}
.customer-checkout-section-panel .customer-checkout-options label:has(input:checked) {
    color: #fff5cf;
    background: linear-gradient(110deg, rgba(0, 229, 212, .15), rgba(255, 215, 0, .075));
    border-color: rgba(255, 215, 0, .7);
    box-shadow: inset 0 0 18px rgba(0, 229, 212, .055), 0 0 15px rgba(255, 215, 0, .07);
}
.customer-checkout-section-panel .customer-checkout-field {
    color: #e4e8f1;
}
.customer-checkout-section-panel .customer-checkout-field > input,
.customer-checkout-section-panel .customer-checkout-field > select,
.customer-checkout-section-panel .customer-checkout-field > textarea {
    background: rgba(5, 8, 17, .88);
    border-color: rgba(0, 229, 212, .38);
    box-shadow: inset 0 1px 4px rgba(0, 0, 0, .22);
}
.customer-checkout-section-panel .customer-checkout-field > input:focus,
.customer-checkout-section-panel .customer-checkout-field > select:focus,
.customer-checkout-section-panel .customer-checkout-field > textarea:focus {
    border-color: #ffd700;
    box-shadow: 0 0 0 3px rgba(255, 215, 0, .08);
}
.customer-checkout-section-panel .customer-checkout-details {
    padding-top: 12px;
    border-top: 1px solid rgba(0, 229, 212, .15);
}
.customer-checkout-section-panel .customer-checkout-payment-options label {
    justify-content: center;
    text-align: center;
    font-weight: 700;
}
.customer-checkout-organization {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin: 2px 0 18px;
    padding: 7px 12px;
    color: #bafaf3;
    background: rgba(0, 229, 212, .075);
    border: 1px solid rgba(0, 229, 212, .25);
    border-radius: 8px;
    font-size: 1.08rem;
    font-weight: 700;
    letter-spacing: .025em;
}
.customer-checkout-organization::before {
    content: "";
    width: 7px;
    height: 7px;
    flex: 0 0 7px;
    background: #00e5d4;
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(0, 229, 212, .75);
}
.customer-checkout-order-items {
    display: grid;
    gap: 9px;
}
.customer-checkout-order-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 13px 15px;
    background: linear-gradient(105deg, rgba(0, 229, 212, .075), rgba(255, 255, 255, .025) 52%, rgba(255, 215, 0, .045));
    border: 1px solid rgba(0, 229, 212, .2);
    border-left: 3px solid rgba(0, 229, 212, .8);
    border-radius: 11px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .035), 0 5px 15px rgba(0, 0, 0, .12);
}
.customer-checkout-item-info {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 11px;
    min-width: 0;
}
.customer-checkout-item-name {
    color: #f5f2e8;
    font-size: 1.04rem;
    font-weight: 700;
    line-height: 1.35;
}
.customer-checkout-item-quantity {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    padding: 3px 9px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .1);
    border: 1px solid rgba(0, 229, 212, .25);
    border-radius: 20px;
    font-size: .86rem;
    font-weight: 700;
    white-space: nowrap;
}
.customer-checkout-item-price {
    color: #ffd700;
    font-size: 1.08rem;
    font-weight: 800;
    text-align: right;
    white-space: nowrap;
    text-shadow: 0 1px 10px rgba(255, 215, 0, .12);
}
.customer-checkout-subtotal {
    align-items: center;
    margin-top: 13px;
    padding: 15px 16px;
    color: #f2ead1;
    background: linear-gradient(100deg, rgba(255, 215, 0, .12), rgba(255, 215, 0, .045));
    border: 1px solid rgba(255, 215, 0, .32);
    border-radius: 11px;
    font-size: 1rem;
    font-weight: 700;
}
.customer-checkout-subtotal strong {
    color: #ffd700;
    font-size: 1.25rem;
    white-space: nowrap;
}
.customer-checkout-delivery-info {
    display: grid;
    gap: 9px;
    margin-top: 14px;
}
.customer-checkout-delivery-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 12px 15px;
    color: #f3edda;
    background: linear-gradient(105deg, rgba(255, 215, 0, .09), rgba(255, 255, 255, .025));
    border: 1px solid rgba(255, 215, 0, .28);
    border-left: 3px solid rgba(255, 215, 0, .85);
    border-radius: 10px;
    line-height: 1.45;
}
.customer-checkout-delivery-row strong {
    color: #ffd700;
    font-size: 1.05rem;
    font-weight: 800;
    white-space: nowrap;
}
.customer-checkout-delivery-minimum {
    background: linear-gradient(105deg, rgba(255, 215, 0, .13), rgba(255, 255, 255, .025));
    border-color: rgba(255, 215, 0, .38);
}
.customer-checkout-delivery-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 10px;
    padding: 15px 16px;
    color: #fff2bd;
    background: linear-gradient(100deg, rgba(255, 215, 0, .17), rgba(255, 215, 0, .055));
    border: 1px solid rgba(255, 215, 0, .48);
    border-radius: 11px;
    font-size: 1.05rem;
    font-weight: 800;
}
.customer-checkout-delivery-total strong {
    color: #ffd700;
    font-size: 1.3rem;
    white-space: nowrap;
    text-shadow: 0 1px 12px rgba(255, 215, 0, .18);
}
@media(max-width:480px) {
    .customer-checkout-delivery-row,
    .customer-checkout-delivery-total {
        align-items: flex-start;
        flex-direction: column;
        gap: 5px;
    }
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
.customer-checkout-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 18px;
    justify-content: center;
}
.customer-checkout-button--secondary {
    text-decoration: none;
}
.customer-checkout-error { color: #ff8585; font-size: .86rem; }
.customer-checkout-note { margin-top: 12px; color: #aeb5c4; font-size: .88rem; line-height: 1.5; }
.customer-checkout-panel[hidden], .customer-checkout-field[hidden] { display: none !important; }
.customer-checkout-save-address { display: flex; align-items: flex-start; gap: 10px; padding: 13px; margin-top: 12px; color: #d8dce8; background: rgba(0, 229, 212, .055); border: 1px solid rgba(0, 229, 212, .28); border-radius: 11px; }
.customer-checkout-save-address input { margin-top: 4px; accent-color: #00e5d4; }
.customer-checkout-address-book { margin-bottom: 14px; }
@media(max-width:700px) {
    .customer-checkout-address-details { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media(max-width:600px) {
    .customer-checkout-panel { padding: 16px; }
}
@media(max-width:420px) {
    .customer-checkout-address-details { grid-template-columns: 1fr; }
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

    <div class="customer-checkout-panel customer-checkout-order-panel">
        <h1 class="customer-checkout-title">Оформление заказа</h1>
        <div class="customer-checkout-organization">{{ $organization->name }}</div>

        <div class="customer-checkout-order-items">
            @foreach($lines as $line)
                <div class="customer-checkout-order-item">
                    <div class="customer-checkout-item-info">
                        <span class="customer-checkout-item-name">{{ $line['item']->name }}</span>
                        <span class="customer-checkout-item-quantity">× {{ $line['quantity'] }}</span>
                    </div>
                    <strong class="customer-checkout-item-price">
                        {{ number_format((float) $line['line_total'], 2, ',', ' ') }} {{ $currencySymbol }}
                    </strong>
                </div>
            @endforeach
        </div>

        <div class="customer-checkout-subtotal">
            <span>Промежуточный итог</span>
            <strong>{{ number_format((float) $subtotal, 2, ',', ' ') }} {{ $currencySymbol }}</strong>
        </div>
        <div id="customer-delivery-summary" @if($selectedFulfillment !== 'delivery') hidden @endif>
            <div class="customer-checkout-delivery-info">
                <div class="customer-checkout-delivery-row">
                    <span>🚚 Доставка</span>
                    <strong style="color:#39d98a;">Включена в минимальную сумму заказа для вашей зоны</strong>
                </div>
            </div>
        </div>

    </div>

    <form method="POST" action="{{ route('customer.checkout.store') }}">
        @csrf
        <input
            type="hidden"
            name="idempotency_key"
            value="{{ old('idempotency_key', $idempotencyKey) }}"
        >

        <div class="customer-checkout-panel customer-checkout-section-panel">
            <h2 class="customer-checkout-title">Способ получения</h2>

            @if(!$canUseDelivery && !$canUsePickup && !$canUseDineIn)
                <div class="customer-checkout-error">
                    Ни один способ получения не подходит для текущего состава корзины.
                    Вернитесь в корзину и измените товары.
                </div>
            @endif

            <div class="customer-checkout-options">
                @if($settings->delivery_enabled)
                    <label @if(!$canUseDelivery) class="is-unavailable" @endif>
                        <input
                            type="radio"
                            name="fulfillment_type"
                            value="delivery"
                            @checked($selectedFulfillment === 'delivery')
                            @disabled(!$canUseDelivery)
                        >
                        🚚 Доставка
                    </label>
                @endif

                @if($settings->pickup_enabled)
                    <label @if(!$canUsePickup) class="is-unavailable" @endif>
                        <input
                            type="radio"
                            name="fulfillment_type"
                            value="pickup"
                            @checked($selectedFulfillment === 'pickup')
                            @disabled(!$canUsePickup)
                        >
                        🛍 Самовывоз
                    </label>
                @endif

                @if($settings->dine_in_enabled)
                    <label @if(!$canUseDineIn) class="is-unavailable" @endif>
                        <input
                            type="radio"
                            name="fulfillment_type"
                            value="dine_in"
                            @checked($selectedFulfillment === 'dine_in')
                            @disabled(!$canUseDineIn)
                        >
                        🍽 В заведении
                    </label>
                @endif
            </div>
            @if(!$canUseDelivery && $settings->delivery_enabled)
                <div class="customer-checkout-note">Доставка недоступна: одна или несколько позиций корзины не подходят для доставки.</div>
            @endif
            @if(!$canUsePickup && $settings->pickup_enabled)
                <div class="customer-checkout-note">Самовывоз недоступен: одна или несколько позиций корзины не подходят для самовывоза.</div>
            @endif
            @if(!$canUseDineIn && $settings->dine_in_enabled)
                <div class="customer-checkout-note">Заказ в заведении недоступен для текущего состава корзины.</div>
            @endif
            @error('fulfillment_type')
                <div class="customer-checkout-error">{{ $message }}</div>
            @enderror

            <div class="customer-checkout-grid">
                <div class="customer-checkout-field" id="customer-pickup-address-field" @if($selectedFulfillment !== 'pickup') hidden @endif>
                    Адрес самовывоза
                    <strong>{{ $settings->pickup_address ?: 'Адрес уточняется у организации' }}</strong>
                </div>
                <label class="customer-checkout-field" id="customer-order-time-field">
                    Дата и время заказа
                    <input
                        type="datetime-local"
                        name="requested_at"
                        value="{{ old('requested_at', $requestedAtDefault) }}"
                        min="{{ $requestedAtMinimum }}"
                        @if(!$settings->preorders_enabled) max="{{ $requestedAtMaximum }}" @endif
                        step="{{ max(5, (int) ($settings->time_slot_minutes ?? 15)) * 60 }}"
                        required
                    >
                    <span class="customer-checkout-muted">
                        Ближайшее время — не раньше чем через {{ $minimumLeadMinutes }} минут. Время указано по {{ $settings->timezoneName() }}. Выбрать можно только время работы заведения.
                    </span>
                    @error('requested_at')
                        <span class="customer-checkout-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="customer-checkout-field" id="customer-dine-in-field" @if($selectedFulfillment !== 'dine_in') hidden @endif>
                    Номер браслета или карты сотрудника
                    <input
                        type="text"
                        id="customer-guest-card-identifier"
                        name="guest_card_identifier"
                        value="{{ old('guest_card_identifier') }}"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Введите номер или код карты"
                        @if($selectedFulfillment === 'dine_in') required @endif
                    >
                    @error('guest_card_identifier')
                        <span class="customer-checkout-error">{{ $message }}</span>
                    @enderror
                </label>
            </div>
        </div>

        <div class="customer-checkout-panel customer-checkout-section-panel">
            <h2 class="customer-checkout-title">Контактные данные</h2>

            <div class="customer-checkout-grid">
                <label class="customer-checkout-field">
                    Имя получателя
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
                    Телефон получателя
                    <input type="tel" value="{{ $customer->phone }}" readonly>
                    <span class="customer-checkout-muted">Телефон подтверждён по SMS.</span>
                </label>
            </div>

            <details class="customer-checkout-details">
                <summary>Комментарий к заказу</summary>
                <label class="customer-checkout-field">
                    <textarea name="customer_comment" maxlength="2000" placeholder="Дополнительные пожелания к заказу">{{ old('customer_comment') }}</textarea>
                </label>
            </details>
        </div>

        <div class="customer-checkout-panel" id="customer-delivery-address-panel"
            @if($selectedFulfillment !== 'delivery') hidden @endif
        >
            <h2 class="customer-checkout-title">Адрес доставки</h2>
            <div class="customer-checkout-note">Укажите адрес доставки. Номер дома вводится отдельно.</div>

            @if($savedAddresses->isNotEmpty())
                <label class="customer-checkout-field customer-checkout-address-book">
                    Сохранённые адреса
                    <select id="customer-saved-address">
                        <option value="">Ввести новый адрес</option>
                        @foreach($savedAddresses as $savedAddress)
                            <option value="{{ $savedAddress->id }}"
                                data-address-line="{{ $savedAddress->address_line }}"
                                data-house-number="{{ $savedAddress->house_number }}"
                                data-entrance="{{ $savedAddress->entrance }}"
                                data-floor="{{ $savedAddress->floor }}"
                                data-apartment="{{ $savedAddress->apartment }}"
                                data-intercom="{{ $savedAddress->intercom }}"
                                data-delivery-comment="{{ $savedAddress->delivery_comment }}"
                                data-latitude="{{ $savedAddress->latitude }}"
                                data-longitude="{{ $savedAddress->longitude }}"
                                @selected($defaultAddress?->id === $savedAddress->id)
                            >{{ $savedAddress->is_favorite ? '★ ' : '' }}{{ $savedAddress->label ?: 'Сохранённый адрес' }} — {{ $savedAddress->address_line }}{{ $savedAddress->house_number ? ', дом ' . $savedAddress->house_number : '' }}</option>
                        @endforeach
                    </select>
                    <span class="customer-checkout-muted">Выберите адрес — поля заполнятся автоматически.</span>
                </label>
            @endif

            <div class="customer-checkout-grid">
                <label class="customer-checkout-field">
                    Улица
                    <input type="text" name="address_line" id="customer-address-line"
                        value="{{ old('address_line', $defaultAddress?->address_line) }}"
                        maxlength="500" placeholder="Например, ул. Ленина" autocomplete="address-line1">
                    @error('address_line') <span class="customer-checkout-error">{{ $message }}</span> @enderror
                </label>
                <label class="customer-checkout-field">
                    Номер дома
                    <input type="text" name="house_number" id="customer-address-house-number"
                        value="{{ old('house_number', $defaultAddress?->house_number) }}"
                        maxlength="30" placeholder="Например, 15 корпус 2">
                    @error('house_number') <span class="customer-checkout-error">{{ $message }}</span> @enderror
                </label>
            </div>

            <input type="hidden" name="delivery_latitude" id="customer-delivery-latitude"
                value="{{ old('delivery_latitude', $defaultAddress?->latitude) }}">
            <input type="hidden" name="delivery_longitude" id="customer-delivery-longitude"
                value="{{ old('delivery_longitude', $defaultAddress?->longitude) }}">
            <div class="customer-checkout-address-map-tools">
                <button type="button" id="customer-delivery-find-address">Найти адрес на карте</button>
                <span id="customer-delivery-map-status">
                    @if($defaultAddress?->latitude !== null && $defaultAddress?->longitude !== null)
                        Координаты сохранённого адреса загружены
                    @else
                        Выберите точку доставки на карте
                    @endif
                </span>
            </div>
            <div id="customer-delivery-map"
                data-origin-latitude="{{ $deliveryOrigin['latitude'] ?? '' }}"
                data-origin-longitude="{{ $deliveryOrigin['longitude'] ?? '' }}"
                data-city="{{ $settings->delivery_city }}"
                data-zones="{{ e(json_encode($deliveryZones->map(fn ($zone) => ['max_distance_km' => (float) $zone->max_distance_km, 'minimum_order_amount' => (float) $zone->minimum_order_amount])->values()->all(), JSON_UNESCAPED_UNICODE)) }}"
                class="customer-checkout-address-map"></div>
            <div id="customer-delivery-zone-feedback" class="customer-checkout-note">Расстояние и минимальная сумма будут рассчитаны после выбора точки на карте.</div>
            @error('delivery_latitude') <span class="customer-checkout-error">{{ $message }}</span> @enderror
            @error('delivery_longitude') <span class="customer-checkout-error">{{ $message }}</span> @enderror

            <div class="customer-checkout-grid customer-checkout-address-details">
                <label class="customer-checkout-field">Подъезд
                    <input id="customer-address-entrance" type="text" name="entrance" value="{{ old('entrance', $defaultAddress?->entrance) }}" maxlength="30">
                </label>
                <label class="customer-checkout-field">Этаж
                    <input id="customer-address-floor" type="text" name="floor" value="{{ old('floor', $defaultAddress?->floor) }}" maxlength="30">
                </label>
                <label class="customer-checkout-field">Квартира / офис
                    <input id="customer-address-apartment" type="text" name="apartment" value="{{ old('apartment', $defaultAddress?->apartment) }}" maxlength="30">
                </label>
                <label class="customer-checkout-field">Домофон
                    <input id="customer-address-intercom" type="text" name="intercom" value="{{ old('intercom', $defaultAddress?->intercom) }}" maxlength="50">
                </label>
            </div>

            <details class="customer-checkout-details">
                <summary>Комментарий курьеру</summary>
                <label class="customer-checkout-field">
                    <textarea id="customer-address-comment" name="delivery_comment" maxlength="1000" placeholder="Как найти вход, особенности доставки и т. п.">{{ old('delivery_comment', $defaultAddress?->delivery_comment) }}</textarea>
                </label>
            </details>

            <label class="customer-checkout-save-address">
                <input type="checkbox" name="save_address" value="1" id="customer-save-address" @checked(old('save_address'))>
                <span>Запомнить этот адрес для следующих заказов</span>
            </label>

            <label class="customer-checkout-field" id="customer-address-label-wrap" @if(!old('save_address')) hidden @endif>
                Как назвать адрес?
                <input type="text" name="address_label" id="customer-address-label" value="{{ old('address_label') }}"
                    maxlength="50" placeholder="Например, Дом, Работа, Родители">
                @error('address_label') <span class="customer-checkout-error">{{ $message }}</span> @enderror
            </label>
        </div>



            <div class="customer-checkout-panel customer-checkout-section-panel">
            <h2 class="customer-checkout-title">Способ оплаты</h2>

            <div id="customer-payment-options" class="customer-checkout-options customer-checkout-payment-options" @if($selectedFulfillment === 'dine_in') hidden @endif>
                @if($settings->cash_payment_enabled)
                    <label>
                        <input type="radio" name="payment_method" value="cash_on_receipt"
                            @checked(old('payment_method', $settings->cash_payment_enabled ? 'cash_on_receipt' : 'card_on_receipt') === 'cash_on_receipt')>
                        💵 Наличные при получении
                    </label>
                @endif
                @if($settings->card_payment_enabled)
                    <label>
                        <input type="radio" name="payment_method" value="card_on_receipt"
                            @checked(old('payment_method', $settings->cash_payment_enabled ? 'cash_on_receipt' : 'card_on_receipt') === 'card_on_receipt')>
                        💳 Терминал при получении
                    </label>
                @endif
            </div>

            <input type="hidden" name="payment_method" id="customer-guest-account-payment"
                value="guest_account" @if($selectedFulfillment !== 'dine_in') disabled @endif>

            <div id="customer-guest-account-note" class="customer-checkout-note" @if($selectedFulfillment !== 'dine_in') hidden @endif>
                Оплата наличными и банковской картой при оформлении не требуется. Заказ будет записан на браслет или карту сотрудника, а расчёт произойдёт на кассе при выходе. Подтверждение фактической оплаты через iiko подключим отдельно.
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

        <div class="customer-checkout-actions">
            <button type="submit" class="customer-checkout-button" @disabled(!$canUseDelivery && !$canUsePickup && !$canUseDineIn)>
                Подтвердить заказ
            </button>
            <a
                href="{{ route('customer.cart') }}"
                class="customer-checkout-button customer-checkout-button--secondary"
            >
                Вернуться в корзину
            </a>
        </div>
        </div>
    </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const addressPanel = document.getElementById('customer-delivery-address-panel');
    const deliverySummary = document.getElementById('customer-delivery-summary');
    const orderTimeField = document.getElementById('customer-order-time-field');
    const pickupAddressField = document.getElementById('customer-pickup-address-field');
    const requestedAt = document.querySelector('input[name="requested_at"]');
    const requestedAtLimits = @json($requestedAtLimits);
    const preordersEnabled = @json((bool) $settings->preorders_enabled);
    const paymentOptions = document.getElementById('customer-payment-options');
    const guestAccountPayment = document.getElementById('customer-guest-account-payment');
    const guestAccountNote = document.getElementById('customer-guest-account-note');
    const dineInField = document.getElementById('customer-dine-in-field');
    const guestCardIdentifier = document.getElementById('customer-guest-card-identifier');
    const fulfillmentInputs = document.querySelectorAll('input[name="fulfillment_type"]');
    const saveAddress = document.getElementById('customer-save-address');
    const addressLabelWrap = document.getElementById('customer-address-label-wrap');
    const addressLabel = document.getElementById('customer-address-label');
    const savedAddressSelect = document.getElementById('customer-saved-address');
    const addressFields = {
        address_line: document.getElementById('customer-address-line'),
        house_number: document.getElementById('customer-address-house-number'),
        entrance: document.getElementById('customer-address-entrance'),
        floor: document.getElementById('customer-address-floor'),
        apartment: document.getElementById('customer-address-apartment'),
        intercom: document.getElementById('customer-address-intercom'),
        delivery_comment: document.getElementById('customer-address-comment')
    };
    const latitudeInput = document.getElementById('customer-delivery-latitude');
    const longitudeInput = document.getElementById('customer-delivery-longitude');
    const deliveryMapElement = document.getElementById('customer-delivery-map');
    const mapStatus = document.getElementById('customer-delivery-map-status');
    const zoneFeedback = document.getElementById('customer-delivery-zone-feedback');
    let deliveryMap = null;
    let deliveryMarker = null;
    let deliveryOrigin = null;
    let deliveryZones = [];

    if (deliveryMapElement && window.L) {
        const originLat = Number(deliveryMapElement.dataset.originLatitude);
        const originLng = Number(deliveryMapElement.dataset.originLongitude);
        if (Number.isFinite(originLat) && Number.isFinite(originLng) && deliveryMapElement.dataset.originLatitude !== '' && deliveryMapElement.dataset.originLongitude !== '') {
            deliveryOrigin = {lat: originLat, lng: originLng};
        }
        try { deliveryZones = JSON.parse(deliveryMapElement.dataset.zones || '[]'); } catch (error) {}
        const currentLat = Number(latitudeInput?.value);
        const currentLng = Number(longitudeInput?.value);
        const hasCurrent = latitudeInput?.value !== '' && longitudeInput?.value !== '';
        const center = hasCurrent ? [currentLat, currentLng] : (deliveryOrigin ? [deliveryOrigin.lat, deliveryOrigin.lng] : [0, 0]);
        deliveryMap = L.map(deliveryMapElement).setView(center, hasCurrent || deliveryOrigin ? 14 : 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(deliveryMap);
        if (deliveryOrigin) L.circleMarker([deliveryOrigin.lat, deliveryOrigin.lng], {radius:8,color:'#ffd700',fillColor:'#ffd700',fillOpacity:.9}).addTo(deliveryMap).bindTooltip('Заведение — центр расчёта');
        const setDeliveryPoint = (lat, lng) => {
            latitudeInput.value = Number(lat).toFixed(7);
            longitudeInput.value = Number(lng).toFixed(7);
            if (deliveryMarker) deliveryMarker.setLatLng([lat,lng]);
            else deliveryMarker = L.marker([lat,lng], {draggable:true}).addTo(deliveryMap);
            deliveryMarker.off('dragend');
            deliveryMarker.on('dragend', () => {
                const point = deliveryMarker.getLatLng();
                setDeliveryPoint(point.lat, point.lng);
            });
            mapStatus.textContent = 'Точка доставки выбрана';
            updateZonePreview();
        };
        window.customerSetDeliveryPoint = setDeliveryPoint;
        if (hasCurrent) setDeliveryPoint(currentLat,currentLng);
        deliveryMap.on('click', event => setDeliveryPoint(event.latlng.lat,event.latlng.lng));
        document.getElementById('customer-delivery-find-address')?.addEventListener('click', async () => {
            const query = [addressFields.address_line?.value, addressFields.house_number?.value, deliveryMapElement.dataset.city].filter(Boolean).join(', ');
            if (!query) { mapStatus.textContent = 'Сначала укажите улицу и номер дома.'; return; }
            mapStatus.textContent = 'Ищем адрес…';
            try {
                const response = await fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(query), {headers:{'Accept-Language':'ru'}});
                const results = await response.json();
                if (!results.length) { mapStatus.textContent = 'Адрес не найден. Выберите точку вручную на карте.'; return; }
                const lat = Number(results[0].lat), lng = Number(results[0].lon);
                deliveryMap.setView([lat,lng],17);
                setDeliveryPoint(lat,lng);
            } catch(error) { mapStatus.textContent = 'Не удалось найти адрес. Выберите точку вручную на карте.'; }
        });
        const distanceKm = (a,b) => {
            const rad = value => value * Math.PI / 180;
            const dLat = rad(b.lat-a.lat), dLng = rad(b.lng-a.lng);
            const value = Math.sin(dLat/2)**2 + Math.cos(rad(a.lat))*Math.cos(rad(b.lat))*Math.sin(dLng/2)**2;
            return 6371.0088 * 2 * Math.atan2(Math.sqrt(value),Math.sqrt(1-value));
        };
        window.customerDeliveryDistanceKm = distanceKm;
        window.customerUpdateDeliveryZonePreview = () => updateZonePreview();
        function updateZonePreview() {
            if (!zoneFeedback) return;
            if (!deliveryOrigin || !latitudeInput.value || !longitudeInput.value) {
                zoneFeedback.textContent = 'Выберите точку доставки на карте — без координат нельзя проверить зону.';
                return;
            }
            const distance = distanceKm(deliveryOrigin,{lat:Number(latitudeInput.value),lng:Number(longitudeInput.value)});
            const zone = deliveryZones.find(item => Number(item.max_distance_km) >= distance);
            if (!zone || distance > 10) {
                zoneFeedback.textContent = 'Расстояние ' + distance.toFixed(2).replace('.',',') + ' км. Адрес за пределами доставки (максимум 10 км).';
                return;
            }
            const minimum = Number(zone.minimum_order_amount);
            const basket = Number(@json((float) $subtotal));
            const shortage = Math.max(0, minimum - basket);
            const minimumText = minimum.toLocaleString('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2});
            if (shortage > 0) {
                zoneFeedback.textContent = 'Расстояние ' + distance.toFixed(2).replace('.',',') + ' км · минимум зоны: ' + minimumText + ' {{ $currencySymbol }}. Добавьте ещё ' + shortage.toLocaleString('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' {{ $currencySymbol }}.';
            } else {
                zoneFeedback.textContent = 'Расстояние ' + distance.toFixed(2).replace('.',',') + ' км · минимальная сумма зоны ' + minimumText + ' {{ $currencySymbol }} соблюдена.';
            }
        }
        if (hasCurrent) updateZonePreview();
        setTimeout(() => deliveryMap.invalidateSize(),250);
    }
    function updateFulfillmentFields() {
        const selected = document.querySelector('input[name="fulfillment_type"]:checked');
        const fulfillmentType = selected?.value;
        const isDelivery = fulfillmentType === 'delivery';
        const isPickup = fulfillmentType === 'pickup';
        const isDineIn = fulfillmentType === 'dine_in';
        if (addressPanel) addressPanel.hidden = !isDelivery;
        if (deliverySummary) deliverySummary.hidden = !isDelivery;
        if (orderTimeField) orderTimeField.hidden = false;
        if (pickupAddressField) pickupAddressField.hidden = !isPickup;
        if (requestedAt) {
            requestedAt.required = true;
            const limits = requestedAtLimits[fulfillmentType] || requestedAtLimits.pickup;
            if (limits) {
                requestedAt.min = limits.min;
                if (preordersEnabled) requestedAt.removeAttribute('max');
                else requestedAt.max = limits.max;
                if (!requestedAt.value || requestedAt.value < limits.min || (!preordersEnabled && requestedAt.value > limits.max)) {
                    requestedAt.value = limits.min;
                }
            }
        }
        if (paymentOptions) paymentOptions.hidden = isDineIn;
        if (guestAccountPayment) guestAccountPayment.disabled = !isDineIn;
        if (guestAccountNote) guestAccountNote.hidden = !isDineIn;
        paymentOptions?.querySelectorAll('input[name="payment_method"]').forEach(input => { input.disabled = isDineIn; });
        if (dineInField) dineInField.hidden = !isDineIn;
        if (guestCardIdentifier) guestCardIdentifier.required = isDineIn;
        if (addressFields.address_line) addressFields.address_line.required = isDelivery;
        if (isDelivery && deliveryMap) {
            setTimeout(() => deliveryMap.invalidateSize(), 180);
            window.customerUpdateDeliveryZonePreview?.();
        }

        if (!isDelivery && saveAddress) {
            saveAddress.checked = false;
            updateSaveAddressFields();
        }
    }
    function updateSaveAddressFields() {
        const enabled = Boolean(saveAddress?.checked);
        if (addressLabelWrap) addressLabelWrap.hidden = !enabled;
        if (addressLabel) addressLabel.required = enabled;
    }
    [addressFields.address_line, addressFields.house_number].forEach((field) => {
        field?.addEventListener('input', () => {
            latitudeInput.value = '';
            longitudeInput.value = '';
            if (deliveryMarker && deliveryMap) {
                deliveryMap.removeLayer(deliveryMarker);
                deliveryMarker = null;
            }
            if (mapStatus) mapStatus.textContent = 'Адрес изменён. Выберите новую точку на карте.';
            window.customerUpdateDeliveryZonePreview?.();
        });
    });
    fulfillmentInputs.forEach((input) => input.addEventListener('change', updateFulfillmentFields));
    saveAddress?.addEventListener('change', updateSaveAddressFields);
    savedAddressSelect?.addEventListener('change', () => {
        const option = savedAddressSelect.selectedOptions[0];
        if (!option) return;
        if (!option.value) {
            Object.values(addressFields).forEach((field) => { if (field) field.value = ''; });
            latitudeInput.value = '';
            longitudeInput.value = '';
            if (deliveryMarker && deliveryMap) {
                deliveryMap.removeLayer(deliveryMarker);
                deliveryMarker = null;
            }
            if (mapStatus) mapStatus.textContent = 'Введите адрес и выберите точку на карте.';
            window.customerUpdateDeliveryZonePreview?.();
            return;
        }
        const values = {
            address_line: option.dataset.addressLine,
            house_number: option.dataset.houseNumber,
            entrance: option.dataset.entrance,
            floor: option.dataset.floor,
            apartment: option.dataset.apartment,
            intercom: option.dataset.intercom,
            delivery_comment: option.dataset.deliveryComment
        };
        Object.entries(values).forEach(([key, value]) => {
            if (addressFields[key]) addressFields[key].value = value ?? '';
        });
        latitudeInput.value = option.dataset.latitude || '';
        longitudeInput.value = option.dataset.longitude || '';
        if (latitudeInput.value && longitudeInput.value && deliveryMap) {
            const lat = Number(latitudeInput.value), lng = Number(longitudeInput.value);
            deliveryMap.setView([lat,lng],16);
            window.customerSetDeliveryPoint?.(lat,lng);
            mapStatus.textContent = 'Координаты сохранённого адреса загружены';
        } else {
            mapStatus.textContent = 'У этого адреса нет координат. Выберите дом на карте.';
            window.customerUpdateDeliveryZonePreview?.();
        }
    });
    updateFulfillmentFields();
    updateSaveAddressFields();
})();
</script>
@endsection
