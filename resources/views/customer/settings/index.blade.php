@extends('customer.layouts.workspace')

@section('title_page')
    Настройки клиента
@endsection

@section('customer-content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
.customer-settings-page{width:min(1040px,calc(100% - 20px));margin:14px auto 32px;color:#f4f4f4}
.customer-settings-panel{margin-bottom:12px;padding:15px;background:linear-gradient(145deg,#0b0c18,#14182a 58%,#0b0c18);border:1px solid rgba(0,229,212,.38);border-radius:16px;box-shadow:0 10px 28px rgba(0,0,0,.3)}
.customer-settings-title{margin:0 0 10px;color:#ffd700;font-size:1.25rem}
.customer-settings-field{display:flex;flex-direction:column;gap:4px;margin:5px 0;color:#d8dce8}
.customer-settings-field input,.customer-settings-field textarea{box-sizing:border-box;width:100%;min-height:37px;padding:7px 10px;color:#fff;background:#090c16;border:1px solid rgba(0,229,212,.45);border-radius:9px}
.customer-settings-field textarea{min-height:54px;resize:vertical}
.customer-settings-button{min-height:40px;padding:9px 16px;color:#00e5d4;background:transparent;border:1px solid rgba(0,229,212,.65);border-radius:22px;font-weight:700;cursor:pointer}
.customer-settings-button:hover{color:#081016;background:#00e5d4}
.customer-settings-address{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 0;border-top:1px solid rgba(0,229,212,.16)}
.customer-settings-address:first-of-type{border-top:0}
.customer-settings-muted{color:#aeb5c4;font-size:.9rem}
.customer-settings-actions{display:flex;flex-wrap:wrap;gap:8px}
.customer-settings-map{height:260px;min-height:220px;border:1px solid rgba(0,229,212,.45);border-radius:12px;overflow:hidden;background:#101522}
.customer-settings-check{display:flex;align-items:center;gap:9px;color:#d8dce8;margin:12px 0}
.customer-settings-check input{accent-color:#00e5d4}
.customer-settings-error{color:#ff9999;font-size:.88rem}
.customer-settings-toast{position:fixed;top:18px;right:20px;z-index:2147483647;display:flex;align-items:center;gap:12px;width:min(410px,calc(100vw - 32px));padding:14px 16px 17px;color:#f4f4f4;background:radial-gradient(circle at 0 0,rgba(0,229,212,.16),transparent 48%),linear-gradient(135deg,#101522 0%,#171b2b 55%,#0b0c18 100%);border:1px solid rgba(0,229,212,.58);border-left:3px solid #00e5d4;border-radius:13px;box-shadow:0 14px 38px rgba(0,0,0,.48);overflow:hidden;animation:customer-toast-enter .32s ease-out both}
.customer-settings-toast-icon{flex:0 0 30px;width:30px;height:30px;display:grid;place-items:center;color:#071817;background:linear-gradient(145deg,#65fff0,#00cbbb);border-radius:50%;font-weight:900}
.customer-settings-toast-message{font-weight:600;font-size:13px;line-height:1.5}
.customer-settings-toast-progress{position:absolute;right:0;bottom:0;left:0;height:2px;background:rgba(0,229,212,.14)}
.customer-settings-toast-progress:after{content:'';display:block;width:100%;height:100%;transform-origin:left;background:linear-gradient(90deg,#00e5d4,#ffd700);animation:customer-toast-progress 4.8s linear forwards}
@media(max-width:700px){.customer-settings-toast{top:12px;right:12px;left:12px;width:auto}}
@media(prefers-reduced-motion:reduce){.customer-settings-toast,.customer-settings-toast-progress:after{animation:none!important}}

.customer-profile-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 14px;align-items:start}
.customer-profile-save{display:block;margin-top:7px}
.customer-profile-phone-note{display:block;margin-top:4px;color:#8f9bad;font-size:.78rem}
.customer-settings-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:11px 0;border-top:1px solid rgba(0,229,212,.16)}
.customer-settings-row:first-of-type{border-top:0;padding-top:0}
.customer-settings-row__copy{min-width:0}
.customer-settings-row__copy strong{display:block;color:#f4f4f4}
.customer-settings-row__copy small{display:block;margin-top:3px;color:#aeb5c4;font-size:.82rem}
.customer-push-settings{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0!important;padding:11px 0!important;background:none!important;border:0!important;border-top:1px solid rgba(0,229,212,.16)!important;border-radius:0!important;box-shadow:none!important}
.customer-push-settings__content strong{color:#f4f4f4!important}
.customer-push-settings__content p{margin-top:3px!important}
.customer-push-settings__button{min-height:36px!important;padding:7px 13px!important;font-size:.86rem}
@media(max-width:600px){
 .customer-profile-fields{grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 9px}
 .customer-settings-row,.customer-push-settings{gap:10px}
 .customer-settings-row__copy small{font-size:.76rem}
 .customer-push-settings{align-items:center!important;flex-direction:row!important}
 .customer-push-settings__button{white-space:nowrap}
}
@media(max-width:380px){.customer-profile-fields{grid-template-columns:1fr}}
 .customer-address-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:3px 12px}
.customer-address-fields .customer-settings-field-wide{grid-column:1/-1}
.customer-settings-check{grid-column:1/-1;margin:7px 0}
.customer-settings-actions{gap:6px}
.customer-settings-button{min-height:36px;padding:7px 12px;font-size:.88rem}
@media(max-width:700px){
 .customer-settings-page{width:calc(100% - 14px);margin:8px auto 20px}
 .customer-settings-panel{padding:12px}
 .customer-settings-address{align-items:flex-start;flex-direction:column}
 .customer-address-fields{grid-template-columns:repeat(2,minmax(0,1fr));gap:2px 9px}
 .customer-settings-map{height:220px;min-height:190px}
 .customer-settings-title{font-size:1.1rem}
 .customer-settings-button{min-height:34px;padding:6px 10px}
}
@media(max-width:360px){.customer-address-fields{grid-template-columns:1fr}.customer-address-fields .customer-settings-field-wide,.customer-settings-check{grid-column:auto}}
</style>

<div class="customer-settings-page">
    @if(session('success'))
        <div class="customer-settings-toast" role="status" aria-live="polite">
            <span class="customer-settings-toast-icon">✓</span>
            <span class="customer-settings-toast-message">{{ session('success') }}</span>
            <span class="customer-settings-toast-progress"></span>
        </div>
    @endif
    @if(session('success'))<div class="customer-settings-panel" style="border-color:#00e5a8;color:#00e5a8">{{ session('success') }}</div>@endif

    <section class="customer-settings-panel">
        <h1 class="customer-settings-title">Профиль</h1>
        <form method="POST" action="{{ route('customer.settings.profile.update') }}">
            @csrf
            @method('PUT')
            <div class="customer-profile-fields">
                <div>
                    <label class="customer-settings-field" for="customer-profile-name">Имя
                        <input id="customer-profile-name" type="text" name="name" value="{{ old('name', $customer->name) }}" maxlength="100" required>
                    </label>
                    @error('name')<span class="customer-settings-error">{{ $message }}</span>@enderror
                    <button class="customer-settings-button customer-profile-save" type="submit">Сохранить имя</button>
                </div>
                <label class="customer-settings-field">Телефон
                    <input type="tel" value="{{ $customer->phone }}" readonly aria-readonly="true">
                    <small class="customer-profile-phone-note">Телефон привязан к аккаунту и используется для входа.</small>
                </label>
            </div>
        </form>
    </section>

    <section class="customer-settings-panel">
        <h2 class="customer-settings-title">Уведомления и звук</h2>
        <div class="customer-settings-row">
            <div class="customer-settings-row__copy"><strong>Звуковые уведомления</strong><small>Звук новых заказов и ответов организации.</small></div>
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
                        @if($address->apartment) Кв.: {{ $address->apartment }} · @endif
                        @if($address->floor) Этаж: {{ $address->floor }} · @endif
                        @if($address->intercom) Домофон: {{ $address->intercom }} · @endif
                        @if($address->gate_code) Шлагбаум: {{ $address->gate_code }} · @endif
                        @if($address->latitude && $address->longitude) Точка на карте сохранена @else Точка на карте не указана @endif
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
                        data-gate-code="{{ $address->gate_code }}"
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
            <p class="customer-settings-muted">Выберите дом на карте: адрес заполнится с улицей и номером дома. Затем укажите подъезд, квартиру и способ доступа курьеру.</p>
            <div class="customer-settings-actions">
                <button type="button" class="customer-settings-button" id="customer-address-search">Найти адрес на карте</button>
                <button type="button" class="customer-settings-button" id="customer-address-locate">Моё местоположение</button>
            </div>

            <div class="customer-address-fields">
            <div class="customer-settings-field"><label for="customer-address-label">Название адреса</label><input id="customer-address-label" name="label" maxlength="50" required placeholder="Дом, Работа, Родители"></div>
            <div class="customer-settings-field customer-settings-field-wide"><label for="customer-address-line">Адрес</label><input id="customer-address-line" name="address_line" maxlength="500" required placeholder="Улица, дом, город"></div>
            <div class="customer-settings-field"><label for="customer-address-entrance">Подъезд / вход</label><input id="customer-address-entrance" name="entrance" maxlength="30" placeholder="№ подъезда или вход"></div>
            <div class="customer-settings-field"><label for="customer-address-apartment">Квартира / офис</label><input id="customer-address-apartment" name="apartment" maxlength="30" placeholder="№ квартиры"></div>
            <div class="customer-settings-field"><label for="customer-address-floor">Этаж</label><input id="customer-address-floor" name="floor" maxlength="30" placeholder="Этаж"></div>
            <div class="customer-settings-field"><label for="customer-address-intercom">Домофон / код</label><input id="customer-address-intercom" name="intercom" maxlength="50" placeholder="Код домофона"></div>
            <div class="customer-settings-field"><label for="customer-address-gate-code">Шлагбаум / въезд</label><input id="customer-address-gate-code" name="gate_code" maxlength="100" placeholder="Код или как открыть"></div>
            <div class="customer-settings-field customer-settings-field-wide"><label for="customer-address-comment">Комментарий курьеру</label><textarea id="customer-address-comment" name="delivery_comment" maxlength="1000"></textarea></div>
            <label class="customer-settings-check"><input type="checkbox" name="is_favorite" id="customer-address-favorite" value="1"> Добавить в избранное</label>
            </div>

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
        gateCode: document.getElementById('customer-address-gate-code'),
        comment: document.getElementById('customer-address-comment'),
        latitude: document.getElementById('customer-address-latitude'),
        longitude: document.getElementById('customer-address-longitude'),
        favorite: document.getElementById('customer-address-favorite')
    };

    const defaultCenter = [56.1124, 94.5886]; // Зеленогорск, Красноярский край
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
                    const parts = data.address || {};
                    const street = parts.road || parts.pedestrian || parts.residential || parts.footway || '';
                    const houseNumber = parts.house_number || '';
                    const locality = parts.city || parts.town || parts.village || parts.municipality || 'Зеленогорск';
                    const streetAddress = [street, houseNumber].filter(Boolean).join(', ');
                    if (streetAddress) {
                        fields.address.value = [streetAddress, locality, 'Красноярский край'].join(', ');
                    } else if (data.display_name && !fields.address.value.trim()) {
                        fields.address.value = data.display_name;
                    }
                } catch (error) { console.warn('Address lookup:', error); }
            }, 700);
        }
    }

    map.on('click', event => {
        setMarker(event.latlng.lat, event.latlng.lng, true);
        fields.address.focus();
    });

    document.getElementById('customer-address-search').addEventListener('click', async () => {
        const query = fields.address.value.trim();
        if (!query) { fields.address.focus(); return; }
        const button = document.getElementById('customer-address-search');
        button.disabled = true;
        try {
            const url = new URL('https://nominatim.openstreetmap.org/search');
            const searchQuery = /зеленогорск|красноярск/i.test(query) ? query : query + ', Зеленогорск, Красноярский край, Россия';
            url.search = new URLSearchParams({format:'jsonv2',q:searchQuery,limit:'1',addressdetails:'1'});
            const response = await fetch(url, {headers:{'Accept':'application/json'}});
            if (!response.ok) throw new Error('Не удалось найти адрес на карте.');
            const results = await response.json();
            if (!results.length) { alert('Адрес не найден. Уточните улицу, дом и город.'); return; }
            const point = results[0];
            setMarker(Number(point.lat), Number(point.lon), false);
            const parts = point.address || {};
            const street = parts.road || parts.pedestrian || parts.residential || '';
            const houseNumber = parts.house_number || '';
            if (street && houseNumber) {
                fields.address.value = [street + ', ' + houseNumber, parts.city || parts.town || 'Зеленогорск', 'Красноярский край'].join(', ');
            } else if (point.display_name) {
                fields.address.value = point.display_name;
            }
            map.setView([Number(point.lat), Number(point.lon)], 17);
        } catch (error) {
            alert(error.message || 'Ошибка поиска адреса.');
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('customer-address-locate').addEventListener('click', () => {
        if (!navigator.geolocation) { alert('Геолокация не поддерживается браузером.'); return; }
        navigator.geolocation.getCurrentPosition(position => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            setMarker(lat, lng, true);
            map.setView([lat, lng], 17);
        }, () => alert('Не удалось получить местоположение. Проверьте разрешение браузера.'), {enableHighAccuracy:true,timeout:10000});
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
            fields.gateCode.value = d.gateCode || '';
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
