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
    gap: 16px; align-items: center; padding: 17px; margin: 13px 0;
    background: linear-gradient(135deg, rgba(22, 29, 48, .96), rgba(10, 15, 28, .98));
    border: 1px solid rgba(0, 229, 212, .27); border-radius: 15px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .035), 0 7px 20px rgba(0, 0, 0, .2);
}
.customer-cart-name { color: #fff; font-weight: 700; }
.customer-cart-muted { color: #aeb5c4; font-size: .9rem; }
.customer-cart-price { color: #ffd700; font-weight: 700; white-space: nowrap; }
.customer-cart-actions { display: flex; gap: 10px; align-items: center; }
.customer-cart-stepper { display: inline-flex; align-items: center; overflow: hidden; border: 1px solid rgba(0, 229, 212, .55); border-radius: 11px; background: #090e1a; }
.customer-cart-stepper button { width: 37px; height: 39px; color: #00e5d4; background: rgba(0, 229, 212, .09); border: 0; cursor: pointer; font-size: 1.2rem; font-weight: 800; }
.customer-cart-stepper button:hover { color: #081016; background: #00e5d4; }
.customer-cart-stepper input { width: 42px; min-height: 39px; padding: 4px 2px; color: #fff; background: transparent; border: 0; border-left: 1px solid rgba(0, 229, 212, .2); border-right: 1px solid rgba(0, 229, 212, .2); border-radius: 0; text-align: center; appearance: textfield; -moz-appearance: textfield; }
.customer-cart-stepper input::-webkit-inner-spin-button,
.customer-cart-stepper input::-webkit-outer-spin-button { appearance: none; margin: 0; }
.customer-cart-update { min-height: 35px; padding: 6px 10px; font-size: .82rem; }
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
    .customer-cart-line { grid-template-columns: 1fr; gap: 11px; padding: 14px; }
    .customer-cart-actions { flex-wrap: wrap; justify-content: space-between; }
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
                                <button class="customer-cart-button customer-cart-update" type="submit">Обновить</button>
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
