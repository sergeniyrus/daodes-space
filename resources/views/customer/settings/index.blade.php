@extends('customer.layouts.workspace')

@section('title_page')
    Настройки клиента
@endsection

@section('customer-content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
.customer-settings-page{width:min(960px,calc(100% - 24px));margin:24px auto 50px;color:#f4f4f4}
.customer-settings-panel{margin-bottom:16px;padding:22px;background:linear-gradient(145deg,#0b0c18,#14182a 58%,#0b0c18);border:1px solid rgba(0,229,212,.38);border-radius:16px;box-shadow:0 10px 28px rgba(0,0,0,.3)}
.customer-settings-title{margin:0 0 16px;color:#ffd700;font-size:1.25rem}
.customer-settings-field{display:flex;flex-direction:column;gap:7px;margin:12px 0;color:#d8dce8}
.customer-settings-field input,.customer-settings-field textarea{box-sizing:border-box;width:100%;min-height:43px;padding:10px 12px;color:#fff;background:#090c16;border:1px solid rgba(0,229,212,.45);border-radius:9px}
.customer-settings-field textarea{min-height:78px;resize:vertical}
.customer-settings-button{min-height:40px;padding:9px 16px;color:#00e5d4;background:transparent;border:1px solid rgba(0,229,212,.65);border-radius:22px;font-weight:700;cursor:pointer}
.customer-settings-button:hover{color:#081016;background:#00e5d4}
.customer-settings-address{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 0;border-top:1px solid rgba(0,229,212,.16)}
.customer-settings-address:first-of-type{border-top:0}
.customer-settings-muted{color:#aeb5c4;font-size:.9rem}
.customer-settings-actions{display:flex;flex-wrap:wrap;gap:8px}
.customer-settings-map{height:340px;min-height:260px;border:1px solid rgba(0,229,212,.45);border-radius:12px;overflow:hidden;background:#101522}
.customer-settings-check{display:flex;align-items:center;gap:9px;color:#d8dce8;margin:12px 0}
.customer-settings-check input{accent-color:#00e5d4}
.customer-settings-error{color:#ff9999;font-size:.88rem}
@media(max-width:600px){.customer-settings-panel{padding:16px}.customer-settings-address{align-items:flex-start;flex-direction:column}.customer-settings-map{height:290px}}
</style>

<div class="customer-settings-page">
    @if(session('success'))<div class="customer-settings-panel" style="border-color:#00e5a8;color:#00e5a8">{{ session('success') }}</div>@endif

    <section class="customer-settings-panel">
        <h1 class="customer-settings-title">Профиль</h1>
        <form method="POST" action="{{ route('customer.settings.profile.update') }}">
            @csrf
            @method('PUT')
            <label class="customer-settings-field">Имя
                <input type="text" name="name" value="{{ old('name', $customer->name) }}" maxlength="100" required>
            </label>
            @error('name')<p class="customer-settings-error">{{ $message }}</p>@enderror
            <button class="customer-settings-button" type="submit">Сохранить имя</button>
        </form>
    </section>

    <section class="customer-settings-panel">
        <h2 class="customer-settings-title">Уведомления и звук</h2>
        <p class="customer-settings-muted">Уведомления в кабинете включены постоянно. Здесь можно настроить звук и разрешение на push для этого браузера.</p>
        <div class="customer-settings-address" style="border-top:0;padding-top:0;">
            <div><strong>Звуковые уведомления</strong><div class="customer-settings-muted">Звук новых заказов и ответов организации.</div></div>
            <button type="button" class="customer-settings-button" id="customer-settings-sound" aria-pressed="false">Загрузка…</button>
        </div>
        @include('components.customer-push-subscription')
    </section>

    <section class="customer-settings-panel">
        <h2 class="customer-settings-title">Мои адреса</h2>
        <p class="customer-settings-muted">Добавляйте адреса, отмечайте избранные и указывайте точку на карте. Координаты пригодятся для расчёта зоны и стоимости доставки.</p>

        @forelse($addresses as $address)
            <article class="customer-settings-address">
                <div>
                    <strong>{{ $address->is_favorite ? '★ ' : '' }}{{ $address->label ?: 'Адрес' }}</strong>
                    <div>{{ $address->address_line }}</div>
                    <div class="customer-settings-muted">
                        @if($address->entrance) Подъезд: {{ $address->entrance }} · @endif
                        @if($address->floor) Этаж: {{ $address->floor }} · @endif
                        @if($address->apartment) Кв.: {{ $address->apartment }} @endif
                        @if($address->latitude && $address->longitude) · Точка на карте сохранена @else · Точка на карте не указана @endif
                    </div>
                </div>
                <div class="customer-settings-actions">
                    <button type="button" class="customer-settings-button js-edit-address"
                        data-id="{{ $address->id }}"
                        data-label="{{ $address->label }}"
                        data-address="{{ $address->address_line }}"
                        data-recipient="{{ $address->recipient_name }}"
                        data-phone="{{ $address->recipient_phone }}"
                        data-entrance="{{ $address->entrance }}"
                        data-floor="{{ $address->floor }}"
                        data-apartment="{{ $address->apartment }}"
                        data-intercom="{{ $address->intercom }}"
                        data-comment="{{ $address->delivery_comment }}"
                        data-latitude="{{ $address->latitude }}"
                        data-longitude="{{ $address->longitude }}"
                        data-favorite="{{ $address->is_favorite ? '1' : '0' }}">Изменить</button>
                    <form method="POST" action="{{ route('customer.settings.addresses.favorite', $address) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="is_favorite" value="{{ $address->is_favorite ? 0 : 1 }}">
                        <button class="customer-settings-button" type="submit">{{ $address->is_favorite ? '★ Из избранного' : '☆ В избранное' }}</button>
                    </form>
                    <form method="POST" action="{{ route('customer.settings.addresses.destroy', $address) }}" onsubmit="return confirm('Удалить этот адрес?')">
                        @csrf
                        @method('DELETE')
                        <button class="customer-settings-button" type="submit">Удалить</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="customer-settings-muted">Сохранённых адресов пока нет.</p>
        @endforelse
    </section>

    <section class="customer-settings-panel" id="customer-address-editor">
        <h2 class="customer-settings-title" id="customer-address-editor-title">Добавить адрес</h2>
        <form method="POST" action="{{ route('customer.settings.addresses.store') }}" id="customer-address-form">
            @csrf
            <input type="hidden" name="_method" id="customer-address-method" value="POST">
            <input type="hidden" name="latitude" id="customer-address-latitude">
            <input type="hidden" name="longitude" id="customer-address-longitude">

            <div class="customer-settings-map" id="customer-address-map" aria-label="Карта выбора адреса"></div>
            <p class="customer-settings-muted">Нажмите на карту или перетащите маркер, чтобы указать точное место. Можно также ввести адрес вручную.</p>

            <div class="customer-settings-field"><label for="customer-address-label">Название адреса</label><input id="customer-address-label" name="label" maxlength="50" required placeholder="Дом, Работа, Родители"></div>
            <div class="customer-settings-field"><label for="customer-address-line">Адрес</label><input id="customer-address-line" name="address_line" maxlength="500" required placeholder="Улица, дом, город"></div>
            <div class="customer-settings-field"><label for="customer-address-recipient">Получатель</label><input id="customer-address-recipient" name="recipient_name" maxlength="100" value="{{ $customer->name }}"></div>
            <div class="customer-settings-field"><label for="customer-address-phone">Телефон получателя</label><input id="customer-address-phone" name="recipient_phone" maxlength="20" value="{{ $customer->phone }}"></div>
            <div class="customer-settings-field"><label for="customer-address-entrance">Подъезд</label><input id="customer-address-entrance" name="entrance" maxlength="30"></div>
            <div class="customer-settings-field"><label for="customer-address-floor">Этаж</label><input id="customer-address-floor" name="floor" maxlength="30"></div>
            <div class="customer-settings-field"><label for="customer-address-apartment">Квартира / офис</label><input id="customer-address-apartment" name="apartment" maxlength="30"></div>
            <div class="customer-settings-field"><label for="customer-address-intercom">Домофон</label><input id="customer-address-intercom" name="intercom" maxlength="50"></div>
            <div class="customer-settings-field"><label for="customer-address-comment">Комментарий курьеру</label><textarea id="customer-address-comment" name="delivery_comment" maxlength="1000"></textarea></div>
            <label class="customer-settings-check"><input type="checkbox" name="is_favorite" id="customer-address-favorite" value="1"> Добавить в избранное</label>

            @foreach(['label','address_line','latitude','longitude'] as $field)
                @error($field)<p class="customer-settings-error">{{ $message }}</p>@enderror
            @endforeach

            <div class="customer-settings-actions">
                <button type="submit" class="customer-settings-button">Сохранить адрес</button>
                <button type="button" class="customer-settings-button" id="customer-address-reset">Новый адрес</button>
            </div>
        </form>
    </section>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(() => {
    const soundButton = document.getElementById('customer-settings-sound');
    if (soundButton) {
        const key = 'daodes_order_notification_sound';
        let enabled = false;
        try { enabled = localStorage.getItem(key) === '1'; } catch (error) {}
        const render = () => {
            soundButton.textContent = enabled ? '🔊 Звук включён' : '🔇 Звук выключен';
            soundButton.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        };
        soundButton.addEventListener('click', () => {
            enabled = !enabled;
            try { localStorage.setItem(key, enabled ? '1' : '0'); } catch (error) {}
            render();
            if (enabled && typeof window.playCustomerOrderNotificationSound === 'function') window.playCustomerOrderNotificationSound();
        });
        render();
    }

    const form = document.getElementById('customer-address-form');
    const mapElement = document.getElementById('customer-address-map');
    if (!form || !mapElement || typeof L === 'undefined') return;

    const fields = {
        label: document.getElementById('customer-address-label'),
        address: document.getElementById('customer-address-line'),
        recipient: document.getElementById('customer-address-recipient'),
        phone: document.getElementById('customer-address-phone'),
        entrance: document.getElementById('customer-address-entrance'),
        floor: document.getElementById('customer-address-floor'),
        apartment: document.getElementById('customer-address-apartment'),
        intercom: document.getElementById('customer-address-intercom'),
        comment: document.getElementById('customer-address-comment'),
        latitude: document.getElementById('customer-address-latitude'),
        longitude: document.getElementById('customer-address-longitude'),
        favorite: document.getElementById('customer-address-favorite')
    };

    const defaultCenter = [42.6977, 23.3219];
    const map = L.map(mapElement).setView(defaultCenter, 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    let marker = null;
    let reverseGeocodeTimer = null;

    function setMarker(lat, lng, reverseGeocode) {
        fields.latitude.value = Number(lat).toFixed(7);
        fields.longitude.value = Number(lng).toFixed(7);
        if (!marker) {
            marker = L.marker([lat, lng], {draggable: true}).addTo(map);
            marker.on('dragend', () => {
                const point = marker.getLatLng();
                setMarker(point.lat, point.lng, true);
            });
        } else marker.setLatLng([lat, lng]);
        if (reverseGeocode) {
            clearTimeout(reverseGeocodeTimer);
            reverseGeocodeTimer = setTimeout(async () => {
                try {
                    const url = new URL('https://nominatim.openstreetmap.org/reverse');
                    url.search = new URLSearchParams({format:'jsonv2',lat:String(lat),lon:String(lng)});
                    const response = await fetch(url, {headers:{'Accept':'application/json'}});
                    if (!response.ok) return;
                    const data = await response.json();
                    if (data.display_name && !fields.address.value.trim()) fields.address.value = data.display_name;
                } catch (error) { console.warn('Address lookup:', error); }
            }, 700);
        }
    }

    map.on('click', event => {
        setMarker(event.latlng.lat, event.latlng.lng, true);
        fields.address.focus();
    });

    function resetEditor() {
        form.action = @json(route('customer.settings.addresses.store'));
        document.getElementById('customer-address-method').value = 'POST';
        document.getElementById('customer-address-editor-title').textContent = 'Добавить адрес';
        form.reset();
        fields.recipient.value = @json($customer->name);
        fields.phone.value = @json($customer->phone);
        fields.latitude.value = '';
        fields.longitude.value = '';
        if (marker) { map.removeLayer(marker); marker = null; }
        map.setView(defaultCenter, 12);
    }

    document.querySelectorAll('.js-edit-address').forEach(button => {
        button.addEventListener('click', () => {
            const d = button.dataset;
            form.action = @json(url('/customer/settings/addresses')) + '/' + encodeURIComponent(d.id);
            document.getElementById('customer-address-method').value = 'PUT';
            document.getElementById('customer-address-editor-title').textContent = 'Редактировать адрес';
            fields.label.value = d.label || '';
            fields.address.value = d.address || '';
            fields.recipient.value = d.recipient || '';
            fields.phone.value = d.phone || '';
            fields.entrance.value = d.entrance || '';
            fields.floor.value = d.floor || '';
            fields.apartment.value = d.apartment || '';
            fields.intercom.value = d.intercom || '';
            fields.comment.value = d.comment || '';
            fields.favorite.checked = d.favorite === '1';
            if (d.latitude && d.longitude) {
                setMarker(Number(d.latitude), Number(d.longitude), false);
                map.setView([Number(d.latitude), Number(d.longitude)], 16);
            } else {
                if (marker) { map.removeLayer(marker); marker = null; }
                fields.latitude.value = '';
                fields.longitude.value = '';
                map.setView(defaultCenter, 12);
            }
            document.getElementById('customer-address-editor').scrollIntoView({behavior:'smooth',block:'start'});
            setTimeout(() => map.invalidateSize(), 250);
        });
    });

    document.getElementById('customer-address-reset').addEventListener('click', resetEditor);
    setTimeout(() => map.invalidateSize(), 200);
})();
</script>
@endsection
