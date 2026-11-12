@extends('template')

@section('title_page')
    Активация рабочего кабинета
@endsection

@section('main')
<style>
.employee-activate-page {
    max-width: 460px;
    margin: 40px auto 55px;
    padding: 26px;
    background:
        radial-gradient(circle at top right, rgba(255, 215, 0, .07), transparent 38%),
        radial-gradient(circle at bottom left, rgba(0, 229, 255, .05), transparent 38%),
        linear-gradient(145deg, #0b0c18 0%, #111323 50%, #0b0c18 100%);
    border: 1px solid rgba(255, 215, 0, .65);
    border-radius: 22px;
    color: #fff;
    box-shadow: 0 14px 45px rgba(0, 0, 0, .5);
}
.employee-activate-title {
    margin: 0 0 8px;
    color: gold;
    font-size: 1.5rem;
    text-align: center;
}
.employee-activate-note {
    margin: 0 0 22px;
    color: #aaa;
    text-align: center;
    font-size: .9rem;
    line-height: 1.5;
}
.employee-activate-label {
    display: block;
    margin: 15px 0 7px;
    color: #ddd;
}
.employee-activate-input {
    display: block;
    box-sizing: border-box;
    width: 100%;
    min-height: 46px;
    padding: 10px 13px;
    background: #080a12;
    color: #fff;
    border: 1px solid rgba(0, 229, 255, .55);
    border-radius: 10px;
    outline: none;
}
.employee-activate-input:focus {
    border-color: gold;
    box-shadow: 0 0 9px rgba(255, 215, 0, .2);
}
.employee-activate-error {
    margin-top: 7px;
    color: #ff7777;
    font-size: .85rem;
}
.employee-activate-button {
    display: block;
    width: 100%;
    min-height: 46px;
    margin-top: 24px;
    background: #0b0c18;
    color: gold;
    border: 1px solid gold;
    border-radius: 23px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}
.employee-activate-button:hover {
    background: gold;
    color: #0b0c18;
}
.employee-activate-back {
    display: block;
    margin-top: 18px;
    color: #00e5d4;
    text-align: center;
    text-decoration: none;
}
@media (max-width: 520px) {
    .employee-activate-page {
        margin: 20px 10px 35px;
        padding: 19px;
        border-radius: 17px;
    }
}
</style>

<div class="employee-activate-page">
    <h1 class="employee-activate-title">Подтверждение телефона</h1>
    <p class="employee-activate-note">
        Введите телефон и одноразовый код из SMS. После проверки задайте собственный пароль.
        Код действует 10 минут и может быть использован только один раз.
    </p>

    <form method="POST" action="{{ route('employee.activate.store') }}">
        @csrf

        <label for="activate-phone" class="employee-activate-label">Номер телефона</label>
        <input
            id="activate-phone"
            class="employee-activate-input"
            type="tel"
            name="phone"
            value="{{ old('phone') }}"
            placeholder="+79991234567"
            autocomplete="tel"
            inputmode="tel"
            required
        >
        @error('phone')
            <div class="employee-activate-error">{{ $message }}</div>
        @enderror

        <label for="activate-code" class="employee-activate-label">Код из SMS</label>
        <input
            id="activate-code"
            class="employee-activate-input"
            type="text"
            name="code"
            value="{{ old('code') }}"
            inputmode="numeric"
            pattern="[0-9]{6}"
            minlength="6"
            maxlength="6"
            autocomplete="one-time-code"
            required
        >
        @error('code')
            <div class="employee-activate-error">{{ $message }}</div>
        @enderror

        <label for="activate-password" class="employee-activate-label">Новый пароль</label>
        <input
            id="activate-password"
            class="employee-activate-input"
            type="password"
            name="password"
            minlength="12"
            maxlength="255"
            autocomplete="new-password"
            required
        >
        @error('password')
            <div class="employee-activate-error">{{ $message }}</div>
        @enderror

        <label for="activate-password-confirmation" class="employee-activate-label">Повторите пароль</label>
        <input
            id="activate-password-confirmation"
            class="employee-activate-input"
            type="password"
            name="password_confirmation"
            minlength="12"
            maxlength="255"
            autocomplete="new-password"
            required
        >

        <button type="submit" class="employee-activate-button">
            Подтвердить телефон и установить пароль
        </button>
    </form>

    <a href="{{ route('employee.login') }}" class="employee-activate-back">← Вернуться ко входу</a>
</div>
@endsection
