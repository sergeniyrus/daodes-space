@extends('template')

@section('title_page')
    Клиентский вход DAODES
@endsection

@section('main')
<style>
.customer-auth-page {
    width: min(460px, calc(100% - 24px));
    margin: 38px auto 55px;
    padding: 26px;
    box-sizing: border-box;
    color: #fff;
    background:
        radial-gradient(circle at top right, rgba(255, 215, 0, .07), transparent 38%),
        radial-gradient(circle at bottom left, rgba(0, 229, 255, .05), transparent 38%),
        linear-gradient(145deg, #0b0c18 0%, #111323 50%, #0b0c18 100%);
    border: 1px solid rgba(255, 215, 0, .65);
    border-radius: 22px;
    box-shadow: 0 14px 45px rgba(0, 0, 0, .5);
}
.customer-auth-title {
    margin: 0 0 8px;
    color: gold;
    font-size: 1.5rem;
    text-align: center;
}
.customer-auth-note {
    margin: 0 0 22px;
    color: #aaa;
    font-size: .9rem;
    line-height: 1.5;
    text-align: center;
}
.customer-auth-label {
    display: block;
    margin: 15px 0 7px;
    color: #ddd;
}
.customer-auth-input {
    display: block;
    box-sizing: border-box;
    width: 100%;
    min-height: 46px;
    padding: 10px 13px;
    color: #fff;
    background: #080a12;
    border: 1px solid rgba(0, 229, 255, .55);
    border-radius: 10px;
    outline: none;
}
.customer-auth-input:focus {
    border-color: gold;
    box-shadow: 0 0 9px rgba(255, 215, 0, .2);
}
.customer-auth-button {
    display: block;
    width: 100%;
    min-height: 46px;
    margin-top: 18px;
    color: gold;
    background: #0b0c18;
    border: 1px solid gold;
    border-radius: 23px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}
.customer-auth-button:hover {
    color: #0b0c18;
    background: gold;
    box-shadow: 0 0 14px rgba(255, 215, 0, .3);
}
.customer-auth-status {
    margin-bottom: 16px;
    color: #00e5a8;
    text-align: center;
}
.customer-auth-error {
    margin-top: 7px;
    color: #ff7777;
    font-size: .85rem;
}
.customer-auth-divider {
    height: 1px;
    margin: 24px 0;
    background: linear-gradient(90deg, transparent, rgba(0, 229, 255, .45), transparent);
}
@media (max-width: 520px) {
    .customer-auth-page {
        margin: 20px auto 35px;
        padding: 19px;
        border-radius: 17px;
    }
}
</style>

<div class="customer-auth-page">
    <h1 class="customer-auth-title">Клиентский кабинет</h1>
    <p class="customer-auth-note">
        Вход и регистрация по номеру телефона. Seed-фраза и пароль DAODES не требуются.
    </p>

    @if(session('status'))
        <div class="customer-auth-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('customer.login.request') }}">
        @csrf

        <label for="customer-name" class="customer-auth-label">Ваше имя</label>
        <input
            id="customer-name"
            class="customer-auth-input"
            type="text"
            name="name"
            value="{{ old('name') }}"
            maxlength="100"
            autocomplete="name"
            placeholder="Имя"
        >
        @error('name')
            <div class="customer-auth-error">{{ $message }}</div>
        @enderror

        <label for="customer-phone" class="customer-auth-label">Номер телефона</label>
        <input
            id="customer-phone"
            class="customer-auth-input"
            type="tel"
            name="phone"
            value="{{ old('phone') }}"
            maxlength="32"
            autocomplete="tel"
            inputmode="tel"
            placeholder="+79991234567"
            required
        >
        @error('phone')
            <div class="customer-auth-error">{{ $message }}</div>
        @enderror

        <button type="submit" class="customer-auth-button">
            Получить SMS-код
        </button>
    </form>

    <div class="customer-auth-divider"></div>

    <form method="POST" action="{{ route('customer.login.verify') }}">
        @csrf

        <label for="customer-verify-phone" class="customer-auth-label">Номер телефона</label>
        <input
            id="customer-verify-phone"
            class="customer-auth-input"
            type="tel"
            name="phone"
            value="{{ old('phone') }}"
            maxlength="32"
            autocomplete="tel"
            inputmode="tel"
            placeholder="+79991234567"
            required
        >

        <label for="customer-code" class="customer-auth-label">Код из SMS</label>
        <input
            id="customer-code"
            class="customer-auth-input"
            type="text"
            name="code"
            inputmode="numeric"
            pattern="[0-9]{6}"
            minlength="6"
            maxlength="6"
            autocomplete="one-time-code"
            placeholder="000000"
            required
        >
        @error('code')
            <div class="customer-auth-error">{{ $message }}</div>
        @enderror

        <button type="submit" class="customer-auth-button">
            Войти
        </button>
    </form>
</div>
@endsection
