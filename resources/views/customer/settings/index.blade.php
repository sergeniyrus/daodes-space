@extends('customer.layouts.workspace')

@section('title_page')
    Настройки клиента
<script>
(() => {
    const button = document.getElementById('customer-settings-sound');
    if (!button) return;
    const key = 'daodes_order_notification_sound';
    let enabled = false;
    try { enabled = localStorage.getItem(key) === '1'; } catch (error) {}
    const render = () => {
        button.textContent = enabled ? '🔊 Звук включён' : '🔇 Звук выключен';
        button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    };
    button.addEventListener('click', () => {
        enabled = !enabled;
        try { localStorage.setItem(key, enabled ? '1' : '0'); } catch (error) {}
        render();
        if (enabled && typeof window.playCustomerOrderNotificationSound === 'function') {
            window.playCustomerOrderNotificationSound();
        }
    });
    render();
})();
</script>
@endsection

@section('customer-content')
<style>
.customer-settings-page{width:min(900px,calc(100% - 24px));margin:24px auto 50px;color:#f4f4f4}
.customer-settings-panel{margin-bottom:16px;padding:22px;background:linear-gradient(145deg,#0b0c18,#14182a 58%,#0b0c18);border:1px solid rgba(0,229,212,.38);border-radius:16px;box-shadow:0 10px 28px rgba(0,0,0,.3)}
.customer-settings-title{margin:0 0 16px;color:#ffd700;font-size:1.25rem}
.customer-settings-field{display:flex;flex-direction:column;gap:7px;margin:12px 0;color:#d8dce8}
.customer-settings-field input{box-sizing:border-box;width:100%;min-height:44px;padding:10px 12px;color:#fff;background:#090c16;border:1px solid rgba(0,229,212,.45);border-radius:9px}
.customer-settings-button{min-height:40px;padding:9px 16px;color:#00e5d4;background:transparent;border:1px solid rgba(0,229,212,.65);border-radius:22px;font-weight:700;cursor:pointer}
.customer-settings-button:hover{color:#081016;background:#00e5d4}
.customer-settings-address{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 0;border-top:1px solid rgba(0,229,212,.16)}
.customer-settings-address:first-of-type{border-top:0}
.customer-settings-muted{color:#aeb5c4;font-size:.9rem}
@media(max-width:600px){.customer-settings-panel{padding:16px}.customer-settings-address{align-items:flex-start;flex-direction:column}}
</style>

<div class="customer-settings-page">
    <section class="customer-settings-panel">
        <h1 class="customer-settings-title">Профиль</h1>
        <form method="POST" action="{{ route('customer.settings.profile.update') }}">
            @csrf
            @method('PUT')
            <label class="customer-settings-field">
                Имя
                <input type="text" name="name" value="{{ old('name', $customer->name) }}" maxlength="100" required>
            </label>
            @error('name')<p class="customer-settings-muted">{{ $message }}</p>@enderror
            <button class="customer-settings-button" type="submit">Сохранить имя</button>
        </form>
    </section>

    <section class="customer-settings-panel">
        <h2 class="customer-settings-title">Уведомления и звук</h2>
        <p class="customer-settings-muted">Уведомления в кабинете включены постоянно. Здесь можно настроить звук и разрешение на push для этого браузера.</p>
        <div class="customer-settings-address" style="border-top:0;padding-top:0;">
            <div>
                <strong>Звуковые уведомления</strong>
                <div class="customer-settings-muted">Звук новых заказов и ответов организации.</div>
            </div>
            <button type="button" class="customer-settings-button" id="customer-settings-sound" aria-pressed="false">Загрузка…</button>
        </div>
        @include('components.customer-push-subscription')
    </section>

    <section class="customer-settings-panel">
        <h2 class="customer-settings-title">Избранные адреса</h2>
        <p class="customer-settings-muted">Адреса появляются здесь после сохранения при оформлении заказа. Отметьте те, которыми пользуетесь чаще.</p>
        @forelse($addresses as $address)
            <div class="customer-settings-address">
                <div>
                    <strong>{{ $address->label ?: 'Адрес' }}</strong>
                    <div>{{ $address->address_line }}</div>
                    <div class="customer-settings-muted">
                        @if($address->entrance) Подъезд: {{ $address->entrance }} · @endif
                        @if($address->floor) Этаж: {{ $address->floor }} · @endif
                        @if($address->apartment) Кв.: {{ $address->apartment }} @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('customer.settings.addresses.favorite', $address) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="is_favorite" value="{{ $address->is_favorite ? 0 : 1 }}">
                    <button class="customer-settings-button" type="submit">{{ $address->is_favorite ? '★ В избранном' : '☆ В избранное' }}</button>
                </form>
            </div>
        @empty
            <p class="customer-settings-muted">Сохранённых адресов пока нет. При следующем заказе отметьте «Сохранить адрес».</p>
        @endforelse
    </section>
</div>
@endsection
