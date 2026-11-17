@extends('customer.layouts.workspace')

@section('title_page')
    Корзина
@endsection

@section('customer-content')
<style>
.customer-cart-page {
    width: min(900px, calc(100% - 24px));
    margin: 14px auto 30px;
    color: #f4f4f4;
}
.customer-cart-panel {
    padding: 16px;
    margin-bottom: 12px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .42);
    border-radius: 16px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, .3);
}
.customer-cart-title { margin: 0 0 14px; color: #ffd700; font-size: clamp(1.55rem, 3vw, 2rem); text-align: center; letter-spacing: .03em; }
.customer-cart-organization { margin: 0 0 12px; color: #00e5d4; font-size: 1.08rem; font-weight: 800; }
.customer-cart-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 12px; align-items: center; padding: 12px; margin: 8px 0;
    background: linear-gradient(135deg, rgba(22, 29, 48, .96), rgba(10, 15, 28, .98));
    border: 1px solid rgba(0, 229, 212, .25); border-radius: 12px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .035);
}
.customer-cart-name { color: #fff; font-size: 1rem; font-weight: 800; line-height: 1.3; }
.customer-cart-muted { color: #aeb5c4; font-size: .88rem; margin-top: 3px; }
.customer-cart-price { color: #ffd700; font-size: 1rem; font-weight: 800; white-space: nowrap; }
.customer-cart-actions { display: flex; gap: 8px; align-items: center; }
.customer-cart-update-form { display: flex; align-items: center; gap: 0; }
.customer-cart-stepper { display: inline-flex; align-items: center; overflow: hidden; border: 1px solid rgba(0, 229, 212, .5); border-radius: 9px; background: #090e1a; }
.customer-cart-stepper button { width: 34px; height: 36px; color: #00e5d4; background: rgba(0, 229, 212, .09); border: 0; cursor: pointer; font-size: 1.2rem; font-weight: 800; }
.customer-cart-stepper button:hover { color: #081016; background: #00e5d4; }
.customer-cart-stepper input { width: 38px; min-height: 36px; padding: 3px 1px; color: #fff; background: transparent; border: 0; border-left: 1px solid rgba(0, 229, 212, .2); border-right: 1px solid rgba(0, 229, 212, .2); border-radius: 0; text-align: center; font-size: .95rem; appearance: textfield; -moz-appearance: textfield; }
.customer-cart-stepper input::-webkit-inner-spin-button,
.customer-cart-stepper input::-webkit-outer-spin-button { appearance: none; margin: 0; }
.customer-cart-button {
    min-height: 36px;
    padding: 6px 12px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .06);
    border: 1px solid rgba(0, 229, 212, .6);
    border-radius: 18px;
    cursor: pointer;
    font-weight: 700;
    font-size: .9rem;
}
.customer-cart-button:hover { color: #081016; background: #00e5d4; }
.customer-cart-remove { color: #ff8585; border-color: rgba(255, 100, 100, .55); }
.customer-cart-remove:hover { color: #fff; background: #a83242; border-color: #ff8585; }
.customer-cart-remove-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; min-height: 36px; padding: 0;
    color: #ff8585; background: rgba(255, 100, 100, .07);
    border: 1px solid rgba(255, 100, 100, .45); border-radius: 10px;
}
.customer-cart-remove-icon svg { width: 17px; height: 17px; display: block; }
.customer-cart-remove-icon:hover { color: #fff; background: #a83242; border-color: #ff8585; }
.customer-cart-total {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 14px;
    padding: 12px 14px;
    color: #ffd700;
    background: linear-gradient(110deg, rgba(255, 215, 0, .09), rgba(0, 229, 212, .06));
    border: 1px solid rgba(255, 215, 0, .28);
    border-radius: 11px;
    font-size: 1.05rem;
    font-weight: 800;
}
.customer-cart-links { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 10px; margin-top: 16px; }
.customer-cart-links a { color: #00e5d4; text-decoration: none; }
.customer-cart-links form { margin: 0; }
.customer-cart-nav-button { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 7px 14px; text-decoration: none !important; }
.customer-cart-clear-form { order: 2; }
.customer-cart-checkout { order: 1; }
.customer-cart-back { order: 3; }
.customer-cart-unavailable { color: #ff9292; font-size: .88rem; margin-top: 5px; }
@media(max-width:650px) {
    .customer-cart-page { width: calc(100% - 16px); margin: 8px auto 20px; }
    .customer-cart-panel { padding: 12px; border-radius: 13px; }
    .customer-cart-line {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px 10px;
        padding: 10px;
        margin: 7px 0;
    }
    .customer-cart-line > div:first-child { min-width: 0; }
    .customer-cart-name { font-size: .96rem; }
    .customer-cart-price { font-size: .98rem; align-self: start; }
    .customer-cart-actions { grid-column: 1 / -1; justify-content: space-between; gap: 8px; }
    .customer-cart-links { flex-direction: column; gap: 8px; margin-top: 14px; }
    .customer-cart-links > *, .customer-cart-links form, .customer-cart-links form button { width: 100%; }
    .customer-cart-total { font-size: .98rem; padding: 10px 11px; }
    .customer-cart-clear-form { order: 2; }
    .customer-cart-checkout { order: 1; }
    .customer-cart-back { order: 3; }
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
            <p class="customer-cart-organization">{{ $organization->name }}</p>

            @foreach($lines as $line)
                <div class="customer-cart-line" id="cart-item-{{ $line['item_id'] }}">
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
                            <form method="POST" action="{{ route('customer.cart.update', $line['item']) }}" class="customer-cart-update-form">
                                @csrf
                                @method('PUT')
                                <div class="customer-cart-stepper">
                                    <button type="button" data-cart-quantity-change="-1" aria-label="Уменьшить количество">−</button>
                                    <input type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="99" aria-label="Количество">
                                    <button type="button" data-cart-quantity-change="1" aria-label="Увеличить количество">+</button>
                                </div>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('customer.cart.remove', $line['item_id']) }}">
                            @csrf
                            @method('DELETE')
                            <button class="customer-cart-button customer-cart-remove customer-cart-remove-icon" type="submit" aria-label="Удалить товар из корзины" title="Удалить товар">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 6h18"/>
                                    <path d="M8 6V4h8v2"/>
                                    <path d="m19 6-1 14H6L5 6"/>
                                    <path d="M10 11v5M14 11v5"/>
                                </svg>
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

            @if(!$canOrder)
                <p class="customer-cart-unavailable">
                    Организация временно не принимает онлайн-заказы. Состав корзины сохранён.
                </p>
            @endif

            <div class="customer-cart-links">
                @if($canOrder)
                    <a href="{{ route('customer.checkout') }}" class="customer-cart-button customer-cart-nav-button customer-cart-checkout">
                        Перейти к оформлению
                    </a>
                @endif

                <form method="POST" action="{{ route('customer.cart.clear') }}" class="customer-cart-clear-form" onsubmit="return confirm('Вы действительно хотите очистить всю корзину?');">
                    @csrf
                    @method('DELETE')
                    <button class="customer-cart-button customer-cart-remove customer-cart-nav-button" type="submit">
                        Очистить корзину
                    </button>
                </form>

                <a href="{{ route('customer.catalog', $organization) }}" class="customer-cart-button customer-cart-nav-button customer-cart-back">
                    ← Вернуться в меню
                </a>
            </div>
        @endif
    </div>
</div>

<script>
(() => {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cart-quantity-change]');
        if (!button) return;
        const form = button.closest('form');
        const input = form?.querySelector('input[name="quantity"]');
        if (!input) return;
        const current = Number.parseInt(input.value, 10) || 1;
        const change = Number.parseInt(button.dataset.cartQuantityChange, 10) || 0;
        const next = Math.min(99, Math.max(1, current + change));
        if (next !== current) {
            input.value = String(next);
            form.requestSubmit();
        }
    });
})();
</script>
@endsection
