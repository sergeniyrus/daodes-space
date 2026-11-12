@extends('template')

@section('title_page')
    Корзина
@endsection

@section('main')
<style>
.customer-cart-page {
    width: min(900px, calc(100% - 24px));
    margin: 28px auto 55px;
    color: #f4f4f4;
}
.customer-cart-panel {
    padding: 22px;
    margin-bottom: 16px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .45);
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .35);
}
.customer-cart-title { margin: 0 0 18px; color: #ffd700; }
.customer-cart-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 14px;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid rgba(0, 229, 212, .18);
}
.customer-cart-name { color: #fff; font-weight: 700; }
.customer-cart-muted { color: #aeb5c4; font-size: .9rem; }
.customer-cart-price { color: #ffd700; font-weight: 700; white-space: nowrap; }
.customer-cart-actions { display: flex; gap: 7px; align-items: center; }
.customer-cart-actions input {
    width: 62px;
    min-height: 38px;
    padding: 5px;
    color: #fff;
    background: #090c16;
    border: 1px solid rgba(255, 215, 0, .4);
    border-radius: 8px;
}
.customer-cart-button {
    min-height: 38px;
    padding: 7px 13px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .06);
    border: 1px solid rgba(0, 229, 212, .65);
    border-radius: 20px;
    cursor: pointer;
    font-weight: 700;
}
.customer-cart-button:hover { color: #081016; background: #00e5d4; }
.customer-cart-remove { color: #ff8585; border-color: rgba(255, 100, 100, .6); }
.customer-cart-total {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 20px;
    color: #ffd700;
    font-size: 1.2rem;
    font-weight: 700;
}
.customer-cart-links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
.customer-cart-links a { color: #00e5d4; text-decoration: none; }
.customer-cart-unavailable { color: #ff9292; }
@media(max-width:650px) {
    .customer-cart-panel { padding: 16px; }
    .customer-cart-line { grid-template-columns: 1fr; gap: 9px; }
    .customer-cart-actions { flex-wrap: wrap; }
}
</style>

<div class="customer-cart-page">
    @if(session('success'))
        <div class="customer-cart-panel">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="customer-cart-panel">{{ session('error') }}</div>
    @endif

    <div class="customer-cart-panel">
        <h1 class="customer-cart-title">🛒 Корзина</h1>

        @if(!$organization || $lines->isEmpty())
            <p class="customer-cart-muted">Корзина пока пуста.</p>
            <div class="customer-cart-links">
                <a href="{{ route('customer.login') }}">Войти в клиентский кабинет</a>
            </div>
        @else
            <p class="customer-cart-muted">{{ $organization->name }}</p>

            @foreach($lines as $line)
                <div class="customer-cart-line">
                    <div>
                        @if($line['item'])
                            <div class="customer-cart-name">{{ $line['item']->name }}</div>
                            <div class="customer-cart-muted">
                                {{ number_format((float) $line['item']->price, 2, ',', ' ') }}
                                {{ $organization->orderSettings?->currency }}
                                × {{ $line['quantity'] }}
                            </div>
                        @else
                            <div class="customer-cart-unavailable">
                                Позиция меню больше не существует (ID {{ $line['item_id'] }})
                            </div>
                        @endif
                        @if(!$line['available'])
                            <div class="customer-cart-unavailable">Позиция временно недоступна.</div>
                        @endif
                    </div>

                    <div class="customer-cart-price">
                        @if($line['available'])
                            {{ number_format($line['line_total'], 2, ',', ' ') }}
                            {{ $organization->orderSettings?->currency }}
                        @else
                            —
                        @endif
                    </div>

                    <div class="customer-cart-actions">
                        @if($line['item'] && $line['available'])
                            <form method="POST" action="{{ route('customer.cart.update', $line['item']) }}">
                                @csrf
                                @method('PUT')
                                <input
                                    type="number"
                                    name="quantity"
                                    value="{{ $line['quantity'] }}"
                                    min="1"
                                    max="99"
                                    aria-label="Количество"
                                >
                                <button class="customer-cart-button" type="submit">Обновить</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('customer.cart.remove', $line['item_id']) }}">
                            @csrf
                            @method('DELETE')
                            <button class="customer-cart-button customer-cart-remove" type="submit">
                                Удалить
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach

            <div class="customer-cart-total">
                <span>Промежуточный итог</span>
                <span>
                    {{ number_format((float) $subtotal, 2, ',', ' ') }}
                    {{ $organization->orderSettings?->currency }}
                </span>
            </div>

            @if($canOrder)
                <div style="margin-top: 20px;">
                    <a
                        href="{{ route('customer.checkout') }}"
                        class="customer-cart-button"
                        style="display: inline-flex; align-items: center; text-decoration: none;"
                    >
                        Перейти к оформлению
                    </a>
                </div>
            @endif

            @if(!$canOrder)
                <p class="customer-cart-unavailable">
                    Организация временно не принимает онлайн-заказы. Состав корзины сохранён.
                </p>
            @endif

            <div class="customer-cart-links">
                <a href="{{ route('customer.catalog', $organization) }}">← Вернуться в меню</a>
                <form method="POST" action="{{ route('customer.cart.clear') }}">
                    @csrf
                    @method('DELETE')
                    <button class="customer-cart-button customer-cart-remove" type="submit">
                        Очистить корзину
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
