@extends('template')
@section('title_page') Восстановление доступа сотрудника @endsection
@section('main')
<style>
.staff-recovery{width:min(480px,calc(100% - 24px));margin:35px auto 55px;padding:25px;box-sizing:border-box;color:#fff;background:linear-gradient(145deg,#0b0c18,#111323 55%,#0b0c18);border:1px solid rgba(255,215,0,.65);border-radius:20px;box-shadow:0 14px 45px rgba(0,0,0,.5)}
.staff-recovery h1{margin:0 0 10px;color:gold;text-align:center;font-size:1.45rem}.staff-recovery p{color:#aaa;text-align:center;line-height:1.5;font-size:.9rem}
.staff-recovery label{display:block;margin:14px 0 7px;color:#ddd}.staff-recovery input{width:100%;min-height:44px;padding:10px 12px;box-sizing:border-box;color:#fff;background:#080a12;border:1px solid rgba(0,229,255,.55);border-radius:9px}
.staff-recovery button{width:100%;min-height:45px;margin-top:17px;color:gold;background:#0b0c18;border:1px solid gold;border-radius:22px;font-weight:700;cursor:pointer}.staff-recovery button:hover{color:#0b0c18;background:gold}
.staff-recovery .error{margin-top:6px;color:#ff7777;font-size:.85rem}.staff-recovery .status{color:#00e5a8;text-align:center}.staff-recovery a{display:block;margin-top:18px;color:#00e5d4;text-align:center}
.staff-recovery .step{margin-top:22px;padding-top:19px;border-top:1px solid rgba(0,229,255,.25)}
</style>
<div class="staff-recovery">
    <h1>Восстановление доступа</h1>
    <p>Введите номер телефона. После получения SMS-кода задайте новый пароль.</p>
    @if (session('status')) <div class="status">{{ session('status') }}</div> @endif

    <form method="POST" action="{{ route('employee.password.email') }}">
        @csrf
        <label for="staff-recovery-phone">Номер телефона</label>
        <input id="staff-recovery-phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="+79991234567" autocomplete="tel" inputmode="tel" required>
        @error('phone') <div class="error">{{ $message }}</div> @enderror
        <button type="submit">Получить код подтверждения</button>
    </form>

    @if (session('recovery_step') === 'verify')
        <div class="step">
            <p>Введите код из SMS и придумайте новый пароль. Повторно вводить пароль не нужно.</p>
            <form method="POST" action="{{ route('employee.password.update') }}">
                @csrf
                <input type="hidden" name="phone" value="{{ old('phone', session('recovery_phone')) }}">
                <label for="staff-recovery-code">Код из SMS</label>
                <input id="staff-recovery-code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required>
                @error('code') <div class="error">{{ $message }}</div> @enderror
                <label for="staff-recovery-password">Новый пароль (не менее 12 символов)</label>
                <input id="staff-recovery-password" type="password" name="password" minlength="12" maxlength="255" autocomplete="new-password" required>
                @error('password') <div class="error">{{ $message }}</div> @enderror
                <button type="submit">Сохранить новый пароль</button>
            </form>
        </div>
    @endif

    <a href="{{ route('employee.login') }}">← Вернуться ко входу</a>
</div>
@endsection