@extends('template')

@section('title_page')
    Меню — {{ $organization->name }}
@endsection

@section('main')
<style>
.customer-order-page {
    width: min(1180px, calc(100% - 24px));
    margin: 28px auto 55px;
    color: #f4f4f4;
}
.customer-order-panel {
    padding: 22px;
    margin-bottom: 20px;
    background: linear-gradient(145deg, #0b0c18, #14182a 58%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .45);
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .35);
}
.customer-order-title {
    margin: 0;
    color: #ffd700;
    font-size: clamp(1.35rem, 3vw, 2rem);
}
.customer-order-muted { color: #aeb5c4; }
.customer-order-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 15px;
}
.customer-order-link,
.customer-order-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 8px 16px;
    color: #00e5d4;
    background: rgba(0, 229, 212, .06);
    border: 1px solid rgba(0, 229, 212, .65);
    border-radius: 22px;
    text-decoration: none;
    cursor: pointer;
    font-weight: 700;
}
.customer-order-link:hover,
.customer-order-button:hover {
    color: #081016;
    background: #00e5d4;
}
.customer-order-alert {
    padding: 12px 15px;
    margin-bottom: 15px;
    color: #f4f4f4;
    background: rgba(0, 229, 212, .08);
    border: 1px solid rgba(0, 229, 212, .35);
    border-radius: 10px;
}
.customer-order-alert.error {
    border-color: rgba(255, 100, 100, .55);
    background: rgba(255, 70, 70, .08);
}
.customer-order-page > .customer-order-section {
    position: relative;
    isolation: isolate;
    margin: 26px 0;
    padding: 22px;
    background-color: #111827 !important;
    background-image:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .12), transparent 48%),
        linear-gradient(135deg, #151b2d 0%, #0c1222 55%, #121a2b 100%) !important;
    border: 1px solid rgba(0, 229, 212, .48) !important;
    border-radius: 18px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .045), 0 12px 32px rgba(0, 0, 0, .38);
}
.customer-order-section {
    padding: 0 !important;
    overflow: hidden;
    transition: border-color .2s ease, box-shadow .2s ease;
}
.customer-order-section[open] {
    border-color: rgba(255, 215, 0, .58) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .045), 0 14px 36px rgba(0, 0, 0, .42), 0 0 18px rgba(0, 229, 212, .06);
}
.customer-order-section > summary {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    min-height: 68px;
    padding: 17px 22px;
    color: #ffd700;
    cursor: pointer;
    list-style: none;
    user-select: none;
    background:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .10), transparent 48%),
        linear-gradient(110deg, rgba(0, 229, 212, .075), rgba(255, 255, 255, .018));
    transition: background .2s ease, color .2s ease;
}
.customer-order-section > summary::-webkit-details-marker { display: none; }
.customer-order-section > summary::marker { content: ""; }
.customer-order-section > summary:hover {
    color: #fff1a8;
    background:
        radial-gradient(ellipse at 100% 0%, rgba(255, 215, 0, .15), transparent 48%),
        linear-gradient(110deg, rgba(0, 229, 212, .12), rgba(255, 255, 255, .025));
}
.customer-order-section[open] > summary {
    border-bottom: 1px solid rgba(0, 229, 212, .28);
}
.customer-order-section-title {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
    margin: 0;
    color: inherit;
    font-size: clamp(1.05rem, 2.5vw, 1.35rem);
    font-weight: 800;
    letter-spacing: .025em;
    text-shadow: 0 0 12px rgba(0, 229, 212, .12);
}
.customer-order-section-title::before {
    content: "";
    width: 7px;
    height: 7px;
    flex: 0 0 7px;
    border-radius: 50%;
    background: #00e5d4;
    box-shadow: 0 0 10px rgba(0, 229, 212, .75);
}
.customer-order-section-meta {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    flex: 0 0 auto;
}
.customer-order-section-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    color: #bafaf3;
    background: rgba(0, 229, 212, .09);
    border: 1px solid rgba(0, 229, 212, .3);
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 800;
}
.customer-order-section-chevron {
    width: 11px;
    height: 11px;
    border-right: 2px solid #ffd700;
    border-bottom: 2px solid #ffd700;
    transform: rotate(45deg) translateY(-3px);
    transition: transform .22s ease;
}
.customer-order-section[open] .customer-order-section-chevron {
    transform: rotate(225deg) translate(-2px, -1px);
}
.customer-order-section-content {
    padding: 20px 22px 22px;
}

.customer-order-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 270px), 1fr));
    gap: 14px;
}
.customer-order-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 17px;
    background: rgba(12, 15, 28, .92);
    border: 1px solid rgba(0, 229, 212, .25);
    border-radius: 14px;
}
.customer-order-item h3 { margin: 0; color: #fff; font-size: 1.05rem; }
.customer-order-item p { margin: 0; color: #aeb5c4; line-height: 1.45; }
.customer-order-price { margin-top: auto; color: #ffd700; font-size: 1.12rem; font-weight: 700; }
.customer-order-add { display: flex; gap: 10px; align-items: center; }
.customer-quantity-stepper {
    display: inline-flex;
    align-items: center;
    overflow: hidden;
    border: 1px solid rgba(0, 229, 212, .55);
    border-radius: 11px;
    background: rgba(4, 9, 19, .96);
    box-shadow: inset 0 0 12px rgba(0, 229, 212, .06);
}
.customer-quantity-stepper button {
    width: 38px; height: 40px; color: #00e5d4;
    background: rgba(0, 229, 212, .09); border: 0;
    cursor: pointer; font-size: 1.25rem; font-weight: 800;
}
.customer-quantity-stepper button:hover { color: #081016; background: #00e5d4; }
.customer-quantity-stepper input {
    width: 42px; min-height: 40px; padding: 4px 2px;
    color: #fff; background: transparent; border: 0;
    border-left: 1px solid rgba(0, 229, 212, .2);
    border-right: 1px solid rgba(0, 229, 212, .2);
    border-radius: 0; text-align: center;
    appearance: textfield; -moz-appearance: textfield;
}
.customer-quantity-stepper input::-webkit-inner-spin-button,
.customer-quantity-stepper input::-webkit-outer-spin-button { appearance: none; margin: 0; }
.customer-order-item { scroll-margin-top: 24px; }
.customer-order-disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 600px) {
    .customer-order-panel { padding: 17px; }
    .customer-order-section > summary { min-height: 60px; padding: 15px; }
    .customer-order-section-content { padding: 15px; }
    .customer-order-section-meta { gap: 9px; }
    .customer-order-toolbar { align-items: stretch; flex-direction: column; }
    .customer-order-link { width: 100%; box-sizing: border-box; }
}
</style>

<div class="customer-order-page">
    @if(session('success'))
        <div class="customer-order-alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="customer-order-alert error">{{ session('error') }}</div>
    @endif

    <div class="customer-order-panel">
        <h1 class="customer-order-title">{{ $organization->name }}</h1>
        <div class="customer-order-muted">Меню организации</div>

        <div class="customer-order-toolbar">
            <div class="customer-order-muted">
                @if($canOrder)
                    @if($settings->delivery_enabled) Доставка @endif
                    @if($settings->pickup_enabled) · Самовывоз @endif
                    @if($settings->dine_in_enabled) · В заведении @endif
                    <br>
                    Валюта: {{ $settings->currency }}
                    @if($settings->delivery_enabled)
                        <br>Доставка: {{ number_format((float) $settings->delivery_fee, 2, ',', ' ') }} {{ $settings->currency }}
                    @endif
                    @if($settings->minimum_order)
                        <br>Минимальный заказ: {{ number_format((float) $settings->minimum_order, 2, ',', ' ') }} {{ $settings->currency }}
                    @endif
                    @if($settings->pickup_enabled && $settings->pickup_min_minutes !== null)
                        <br>Самовывоз: от {{ $settings->pickup_min_minutes }} мин.
                    @endif
                @else
                    Организация пока не принимает онлайн-заказы. Меню доступно для просмотра, но добавить блюда в корзину и оформить заказ пока нельзя.
                @endif
            </div>

            @if(auth('customer')->check())
                <a class="customer-order-link" href="{{ route('customer.orders.index') }}">
                    Мои заказы
                </a>
            @endif

            <a class="customer-order-link" href="{{ route('customer.cart') }}">
                🛒 Корзина{{ $cartCount ? ' · ' . $cartCount : '' }}
            </a>
        </div>
    </div>

    @forelse($sections as $section)
        <details class="customer-order-section">
            <summary>
                <span class="customer-order-section-title">{{ $section->name }}</span>
                <span class="customer-order-section-meta">
                    <span class="customer-order-section-count">{{ $section->activeItems->count() }}</span>
                    <span class="customer-order-section-chevron" aria-hidden="true"></span>
                </span>
            </summary>

            <div class="customer-order-section-content">
            <div class="customer-order-grid">
                @forelse($section->activeItems as $item)
                    <article class="customer-order-item" id="menu-item-{{ $item->id }}">
                        <h3>{{ $item->name }}</h3>

                        @if($item->description)
                            <p>{{ $item->description }}</p>
                        @endif

                        @if($item->weight || $item->unit)
                            <p>{{ $item->weight }} {{ $item->unit }}</p>
                        @endif

                        <div class="customer-order-price">
                            {{ number_format((float) $item->price, 2, ',', ' ') }}
                            @if($settings?->currency)
                                {{ $settings->currency }}
                            @endif
                        </div>

                        @if($canOrder)
                            <form
                                method="POST"
                                action="{{ route('customer.cart.add', $organization) }}"
                                class="customer-order-add"
                            >
                                @csrf
                                <input type="hidden" name="item_id" value="{{ $item->id }}">
                                <div class="customer-quantity-stepper">
                                    <button type="button" data-quantity-change="-1" aria-label="Уменьшить количество">−</button>
                                    <input type="number" name="quantity" value="1" min="1" max="99" aria-label="Количество">
                                    <button type="button" data-quantity-change="1" aria-label="Увеличить количество">+</button>
                                </div>
                                <button type="submit" class="customer-order-button">
                                    Добавить
                                </button>
                            </form>
                        @else
                            <button type="button" class="customer-order-button customer-order-disabled" disabled>
                                Заказ недоступен
                            </button>
                        @endif
                    </article>
                @empty
                    <p class="customer-order-muted">В этом разделе пока нет доступных блюд.</p>
                @endforelse
            </div>
            </div>
        </details>
    @empty
        <div class="customer-order-panel customer-order-muted">
            Меню пока пустое.
        </div>
    @endforelse
</div>

<script>
(() => {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-quantity-change]');
        if (!button) return;
        const input = button.closest('.customer-quantity-stepper')?.querySelector('input[name="quantity"]');
        if (!input) return;
        const current = Number.parseInt(input.value, 10) || 1;
        const change = Number.parseInt(button.dataset.quantityChange, 10) || 0;
        input.value = String(Math.min(99, Math.max(1, current + change)));
    });
})();
</script>
@endsection
