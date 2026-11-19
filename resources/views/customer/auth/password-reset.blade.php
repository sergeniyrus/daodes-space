@extends('template')
@section('title_page') Регистрация и восстановление доступа — Ёлки Иголки @endsection
@section('main')
<style>
.customer-recovery{width:min(480px,calc(100% - 24px));margin:35px auto 55px;padding:25px;box-sizing:border-box;color:#fff;background:linear-gradient(145deg,#0b0c18,#111323 55%,#0b0c18);border:1px solid rgba(255,215,0,.65);border-radius:20px;box-shadow:0 14px 45px rgba(0,0,0,.5)}
.customer-recovery h1{margin:0 0 10px;color:gold;text-align:center;font-size:1.45rem}.customer-recovery p{color:#aaa;text-align:center;line-height:1.5;font-size:.9rem}
.customer-recovery label{display:block;margin:14px 0 7px;color:#ddd}.customer-recovery input{width:100%;min-height:44px;padding:10px 12px;box-sizing:border-box;color:#fff;background:#080a12;border:1px solid rgba(0,229,255,.55);border-radius:9px}
.customer-recovery button{width:100%;min-height:45px;margin-top:17px;color:gold;background:#0b0c18;border:1px solid gold;border-radius:22px;font-weight:700;cursor:pointer}.customer-recovery button:hover{color:#0b0c18;background:gold}
.customer-recovery .error{margin-top:6px;color:#ff7777;font-size:.85rem}.customer-recovery .status{color:#00e5a8;text-align:center}.customer-recovery a{display:block;margin-top:18px;color:#00e5d4;text-align:center}
.customer-recovery .step{margin-top:22px;padding-top:19px;border-top:1px solid rgba(0,229,255,.25)}
</style>
<div class="customer-recovery">
    <h1>Регистрация / восстановление пароля</h1>
    <p>Введите телефон. Сначала подтвердим номер SMS-кодом, затем вы зададите пароль для входа без SMS.</p>
    @if (session('status')) <div class="status">{{ session('status') }}</div> @endif

    <form method="POST" action="{{ route('customer.password.email') }}">
        @csrf
        <label for="customer-recovery-name">Ваше имя (при первой регистрации)</label>
        <input id="customer-recovery-name" type="text" name="name" value="{{ old('name') }}" maxlength="100" autocomplete="name">
        <label for="customer-recovery-phone">Номер телефона</label>
        <input id="customer-recovery-phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="+79991234567" autocomplete="tel" inputmode="tel" required>
        @error('phone') <div class="error">{{ $message }}</div> @enderror
        <button type="submit">Получить код подтверждения</button>
    </form>

    @if (session('recovery_step') === 'verify')
        <div class="step">
            <p>Введите код из SMS и придумайте пароль. Повторно вводить пароль не нужно.</p>
            <form method="POST" action="{{ route('customer.password.update') }}">
                @csrf
                <input type="hidden" name="phone" value="{{ old('phone', session('recovery_phone')) }}">
                <label for="customer-recovery-code">Код из SMS</label>
                <input id="customer-recovery-code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required>
                @error('code') <div class="error">{{ $message }}</div> @enderror
                <label for="customer-recovery-password">Придумайте пароль (не менее 12 символов)</label>
                <input id="customer-recovery-password" type="password" name="password" minlength="12" maxlength="255" autocomplete="new-password" required>
                @error('password') <div class="error">{{ $message }}</div> @enderror
                <button type="submit">Подтвердить телефон и сохранить пароль</button>
            </form>
        </div>
    @endif

    <a href="{{ route('customer.login') }}">← Вернуться ко входу клиента</a>
</div>
@endsection