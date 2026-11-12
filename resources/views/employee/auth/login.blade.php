@extends('template')

@section('title_page')
    Рабочий кабинет — вход сотрудника
@endsection

@section('main')

<style>
.employee-auth-page {
    max-width: 460px;
    margin: 40px auto 55px;
    padding: 26px;

    background:
        radial-gradient(circle at top right, rgba(255, 215, 0, 0.07), transparent 38%),
        radial-gradient(circle at bottom left, rgba(0, 229, 255, 0.05), transparent 38%),
        linear-gradient(145deg, #0b0c18 0%, #111323 50%, #0b0c18 100%);

    border: 1px solid rgba(255, 215, 0, 0.65);
    border-radius: 22px;
    color: #fff;
    box-shadow: 0 14px 45px rgba(0, 0, 0, 0.5);
}

.employee-auth-title {
    margin: 0 0 8px;
    color: gold;
    font-size: 1.55rem;
    text-align: center;
    text-shadow: 0 0 10px rgba(255, 215, 0, 0.2);
}

.employee-auth-subtitle {
    margin: 0 0 24px;
    color: #aaa;
    text-align: center;
    font-size: 0.9rem;
}

.employee-auth-label {
    display: block;
    margin: 15px 0 7px;
    color: #ddd;
}

.employee-auth-input {
    display: block;
    box-sizing: border-box;
    width: 100%;
    min-height: 46px;
    padding: 10px 13px;

    background: #080a12;
    color: #fff;
    border: 1px solid rgba(0, 229, 255, 0.55);
    border-radius: 10px;
    outline: none;
}

.employee-auth-input:focus {
    border-color: gold;
    box-shadow: 0 0 9px rgba(255, 215, 0, 0.2);
}

.employee-auth-error {
    margin-top: 7px;
    color: #ff7777;
    font-size: 0.85rem;
}

.employee-auth-status {
    margin-bottom: 15px;
    color: #00e5a8;
    text-align: center;
}

.employee-auth-button {
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
    transition: 0.2s;
}

.employee-auth-button:hover {
    background: gold;
    color: #0b0c18;
    box-shadow: 0 0 14px rgba(255, 215, 0, 0.3);
}

.employee-auth-note {
    margin-top: 18px;
    color: #888;
    font-size: 0.8rem;
    line-height: 1.5;
    text-align: center;
}

@media (max-width: 520px) {
    .employee-auth-page {
        margin: 20px 10px 35px;
        padding: 19px;
        border-radius: 17px;
    }

    .employee-auth-title {
        font-size: 1.3rem;
    }
}
</style>

<div class="employee-auth-page">

    <h1 class="employee-auth-title">
        Рабочий кабинет
    </h1>

    <p class="employee-auth-subtitle">
        Вход для сотрудников организаций DAODES
    </p>

    @if (session('status'))
        <div class="employee-auth-status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('employee.login.store') }}">
        @csrf

        <div>
            <label for="employee-phone" class="employee-auth-label">
                Номер телефона
            </label>

            <input
                id="employee-phone"
                class="employee-auth-input"
                type="tel"
                name="phone"
                value="{{ old('phone') }}"
                placeholder="+79991234567"
                autocomplete="username"
                inputmode="tel"
                required
                autofocus
            >

            @error('phone')
                <div class="employee-auth-error">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div>
            <label for="employee-password" class="employee-auth-label">
                Пароль
            </label>

            <input
                id="employee-password"
                class="employee-auth-input"
                type="password"
                name="password"
                autocomplete="current-password"
                required
            >

            @error('password')
                <div class="employee-auth-error">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <button type="submit" class="employee-auth-button">
            Войти
        </button>
    </form>

    <div class="employee-auth-note">
        Для входа необходим подтверждённый номер телефона
        и активное членство хотя бы в одной организации.
    </div>

</div>

@endsection