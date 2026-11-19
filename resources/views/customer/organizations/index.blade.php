@extends('template')

@section('title_page')
    Выбор организации
@endsection

@section('main')
<style>
.customer-organizations {
    width: min(900px, calc(100% - 24px));
    margin: 35px auto 55px;
    padding: 25px;
    box-sizing: border-box;
    color: #fff;
    background:
        radial-gradient(circle at top right, rgba(255, 215, 0, .06), transparent 36%),
        linear-gradient(145deg, #0b0c18 0%, #111323 55%, #0b0c18 100%);
    border: 1px solid rgba(255, 215, 0, .6);
    border-radius: 20px;
    box-shadow: 0 14px 40px rgba(0, 0, 0, .45);
}
.customer-organizations-title {
    margin: 0 0 8px;
    color: gold;
    font-size: 1.55rem;
    text-align: center;
}
.customer-organizations-subtitle {
    margin: 0 auto 22px;
    max-width: 600px;
    color: #aaa;
    line-height: 1.5;
    text-align: center;
}
.customer-organizations-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 13px;
}
.customer-organization-card {
    display: block;
    padding: 19px;
    color: #fff;
    text-decoration: none;
    background: rgba(255, 255, 255, .025);
    border: 1px solid rgba(0, 229, 255, .38);
    border-radius: 14px;
    transition: .2s;
}
.customer-organization-card:hover {
    color: #fff;
    border-color: gold;
    transform: translateY(-2px);
    box-shadow: 0 0 15px rgba(255, 215, 0, .12);
}
.customer-organization-name {
    color: gold;
    font-size: 1.1rem;
    font-weight: 700;
}
.customer-organization-action {
    margin-top: 10px;
    color: #00e5d4;
    font-size: .86rem;
}
.customer-organizations-empty {
    padding: 25px 10px;
    color: #aaa;
    text-align: center;
}
.customer-organizations-login {
    margin-top: 22px;
    color: #999;
    font-size: .9rem;
    text-align: center;
}
.customer-organizations-login a {
    color: #00e5d4;
}
@media (max-width: 560px) {
    .customer-organizations {
        margin: 20px auto 35px;
        padding: 18px;
    }
}
</style>

<div class="customer-organizations">
    <h1 class="customer-organizations-title">Выберите организацию</h1>

    <p class="customer-organizations-subtitle">
        Каталоги и заказы разных организаций разделены.
        Выберите заведение, чтобы посмотреть меню и оформить заказ.
    </p>

    @if ($organizations->isNotEmpty())
        <div class="customer-organizations-list">
            @foreach ($organizations as $organization)
                <a
                    href="{{ route('customer.catalog', $organization) }}"
                    class="customer-organization-card"
                >
                    <div class="customer-organization-name">
                        {{ $organization->name }}
                    </div>

                    <div class="customer-organization-action">
                        Перейти к меню →
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="customer-organizations-empty">
            Пока нет организаций с доступным каталогом.
        </div>
    @endif

    @guest('customer')
        <div class="customer-organizations-login">
            Уже есть клиентская учётная запись?
            <a href="{{ route('customer.login') }}">Войти по телефону</a>
        </div>
    @endguest
</div>
@endsection
