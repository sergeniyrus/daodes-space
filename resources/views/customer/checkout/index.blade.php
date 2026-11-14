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
            <span>Промежуточный итог</span>
            <span>{{ number_format((float) $subtotal, 2, ',', ' ') }} {{ $settings->currency }}</span>
        </div>
        <div id="customer-delivery-summary" @if(old('fulfillment_type', $settings->pickup_enabled ? 'pickup' : ($settings->dine_in_enabled ? 'dine_in' : 'delivery')) !== 'delivery') hidden @endif>
        @if($minimumOrder !== null)
            <div class="customer-checkout-note">
                Минимальная сумма товаров для доставки:
                <strong>{{ number_format((float) $minimumOrder, 2, ',', ' ') }} {{ $settings->currency }}</strong>
            </div>
        @endif
        <div class="customer-checkout-line">
            <span>Доставка (будет добавлена только при доставке)</span>
            <span>{{ number_format((float) $deliveryFee, 2, ',', ' ') }} {{ $settings->currency }}</span>
        </div>
        <div class="customer-checkout-total">
            <span>Итого при доставке</span>
            <span>{{ number_format((float) $deliveryTotal, 2, ',', ' ') }} {{ $settings->currency }}</span>
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
                <label class="customer-checkout-field" id="customer-pickup-time-field" @if(old('fulfillment_type', $settings->pickup_enabled ? 'pickup' : ($settings->dine_in_enabled ? 'dine_in' : 'delivery')) !== 'pickup') hidden @endif>
                    Время самовывоза
                    <input
                        type="datetime-local"
                        name="requested_at"
                        value="{{ old('requested_at', now()->addMinutes(max(30, (int) ($settings->pickup_min_minutes ?? 0)))->ceilUnit('minute')->format('Y-m-d\TH:i')) }}"
                        min="{{ now()->addMinutes(max(30, (int) ($settings->pickup_min_minutes ?? 0)))->ceilUnit('minute')->format('Y-m-d\TH:i') }}"
                    >
                    <span class="customer-checkout-muted">
                        Минимальное время приготовления — 30 минут.
                    </span>
                    @error('requested_at')
                        <span class="customer-checkout-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="customer-checkout-field" id="customer-dine-in-field" @if(old('fulfillment_type', $settings->pickup_enabled ? 'pickup' : ($settings->dine_in_enabled ? 'dine_in' : 'delivery')) !== 'dine_in') hidden @endif>
                    Номер браслета
                    <input
                        type="text"
                        name="table_number"
                        value="{{ old('table_number') }}"
                        maxlength="30"
                        placeholder="Например, 125"
                    >
                </label>
            </div>
        </div>

        <div class="customer-checkout-panel">
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
            @if(old('fulfillment_type', $settings->pickup_enabled ? 'pickup' : ($settings->dine_in_enabled ? 'dine_in' : 'delivery')) !== 'delivery') hidden @endif
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
                                data-entrance="{{ $savedAddress->entrance }}"
                                data-floor="{{ $savedAddress->floor }}"
                                data-apartment="{{ $savedAddress->apartment }}"
                                data-intercom="{{ $savedAddress->intercom }}"
                                data-delivery-comment="{{ $savedAddress->delivery_comment }}"
                                @selected($loop->first && old('address_line', $defaultAddress?->address_line) === $savedAddress->address_line)
                            >{{ $savedAddress->label ?: 'Сохранённый адрес' }} — {{ $savedAddress->address_line }}</option>
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
                        value="{{ old('house_number') }}"
                        maxlength="30" placeholder="Например, 15 корпус 2">
                    @error('house_number') <span class="customer-checkout-error">{{ $message }}</span> @enderror
                </label>
            </div>

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



            <div class="customer-checkout-panel">
            <h2 class="customer-checkout-title">Способ оплаты</h2>

            <div class="customer-checkout-options customer-checkout-payment-options">
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

        <div class="customer-checkout-actions">
            <button type="submit" class="customer-checkout-button">
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

<script>
(() => {
    const addressPanel = document.getElementById('customer-delivery-address-panel');
    const deliverySummary = document.getElementById('customer-delivery-summary');
    const pickupTimeField = document.getElementById('customer-pickup-time-field');
    const requestedAt = document.querySelector('input[name="requested_at"]');
    const dineInField = document.getElementById('customer-dine-in-field');
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
    function updateFulfillmentFields() {
        const selected = document.querySelector('input[name="fulfillment_type"]:checked');
        const fulfillmentType = selected?.value;
        const isDelivery = fulfillmentType === 'delivery';
        const isPickup = fulfillmentType === 'pickup';
        const isDineIn = fulfillmentType === 'dine_in';
        if (addressPanel) addressPanel.hidden = !isDelivery;
        if (deliverySummary) deliverySummary.hidden = !isDelivery;
        if (pickupTimeField) pickupTimeField.hidden = !isPickup;
        if (requestedAt) requestedAt.required = isPickup;
        if (dineInField) dineInField.hidden = !isDineIn;
        if (addressFields.address_line) addressFields.address_line.required = isDelivery;

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
    fulfillmentInputs.forEach((input) => input.addEventListener('change', updateFulfillmentFields));
    saveAddress?.addEventListener('change', updateSaveAddressFields);
    savedAddressSelect?.addEventListener('change', () => {
        const option = savedAddressSelect.selectedOptions[0];
        if (!option || !option.value) return;
        const values = {
            address_line: option.dataset.addressLine,
            house_number: '',
            entrance: option.dataset.entrance,
            floor: option.dataset.floor,
            apartment: option.dataset.apartment,
            intercom: option.dataset.intercom,
            delivery_comment: option.dataset.deliveryComment
        };
        Object.entries(values).forEach(([key, value]) => {
            if (addressFields[key]) addressFields[key].value = value ?? '';
        });
    });
    updateFulfillmentFields();
    updateSaveAddressFields();
})();
</script>
@endsection
