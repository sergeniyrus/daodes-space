@extends('template')

@section('title_page')
    Вход в DAODES
@endsection

@section('main')
<style>
.auth-portal {
    width: min(760px, calc(100% - 24px));
    margin: 38px auto 55px;
    padding: 28px;
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
.auth-portal-title {
    margin: 0 0 9px;
    color: gold;
    font-size: 1.8rem;
    text-align: center;
    text-shadow: 0 0 12px rgba(255, 215, 0, .2);
}
.auth-portal-subtitle {
    margin: 0 auto 27px;
    max-width: 560px;
    color: #aaa;
    line-height: 1.55;
    text-align: center;
}
.auth-portal-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.auth-portal-option {
    display: flex;
    min-height: 190px;
    padding: 20px 15px;
    box-sizing: border-box;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #fff;
    text-align: center;
    text-decoration: none;
    background: linear-gradient(145deg, rgba(255,255,255,.045), rgba(255,255,255,.01));
    border: 1px solid rgba(0, 229, 255, .42);
    border-radius: 16px;
    transition: .2s ease;
}
.auth-portal-option:hover {
    color: #fff;
    border-color: gold;
    transform: translateY(-3px);
    box-shadow: 0 0 18px rgba(255, 215, 0, .13);
}
.auth-portal-option-icon {
    display: grid;
    width: 54px;
    height: 54px;
    place-items: center;
    color: gold;
    font-size: 27px;
    border: 1px solid rgba(255, 215, 0, .55);
    border-radius: 50%;
}
.auth-portal-option-title {
    color: gold;
    font-size: 1.05rem;
    font-weight: 700;
}
.auth-portal-option-description {
    color: #aaa;
    font-size: .83rem;
    line-height: 1.45;
}
.auth-portal-note {
    margin-top: 22px;
    color: #777;
    font-size: .82rem;
    line-height: 1.5;
    text-align: center;
}
@media (max-width: 650px) {
    .auth-portal {
        margin: 20px auto 35px;
        padding: 20px 15px;
        border-radius: 17px;
    }
    .auth-portal-title {
        font-size: 1.5rem;
    }
    .auth-portal-options {
        grid-template-columns: 1fr;
    }
    .auth-portal-option {
        min-height: 125px;
        padding: 16px;
    }
}
</style>

<div class="auth-portal">
    <h1 class="auth-portal-title">Вход в DAODES</h1>

    <p class="auth-portal-subtitle">
        Выберите кабинет, в который хотите войти.
        Для партнёров, сотрудников и клиентов предусмотрены отдельные способы авторизации.
    </p>

    <div class="auth-portal-options">
        <a href="{{ route('partner.login') }}" class="auth-portal-option">
            <span class="auth-portal-option-icon" aria-hidden="true">◆</span>
            <span class="auth-portal-option-title">Партнёры DAODES</span>
            <span class="auth-portal-option-description">
                Учётная запись проекта, пароль и seed-фраза для доступа к функциям DAODES.
            </span>
        </a>

        <a href="{{ route('employee.login') }}" class="auth-portal-option">
            <span class="auth-portal-option-icon" aria-hidden="true">⚙</span>
            <span class="auth-portal-option-title">Сотрудники организаций</span>
            <span class="auth-portal-option-description">
                Вход по подтверждённому телефону и паролю в рабочий кабинет.
            </span>
        </a>

        <a href="{{ route('customer.login') }}" class="auth-portal-option">
            <span class="auth-portal-option-icon" aria-hidden="true">🛍</span>
            <span class="auth-portal-option-title">Клиенты организаций</span>
            <span class="auth-portal-option-description">
                Вход по номеру телефона и паролю. SMS — только при регистрации и восстановлении доступа.
            </span>
        </a>
    </div>

    <div class="auth-portal-note">
        Партнёрская, рабочая и клиентская учётные записи независимы друг от друга.
    </div>
</div>
@endsection
