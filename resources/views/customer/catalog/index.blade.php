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
.customer-order-section { margin: 26px 0; }
.customer-order-section h2 {
    padding-bottom: 10px;
    color: #00e5d4;
    border-bottom: 1px solid rgba(0, 229, 212, .25);
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
.customer-order-add { display: flex; gap: 8px; align-items: center; }
.customer-order-add input {
    width: 64px;
    min-height: 40px;
    padding: 6px;
    color: #fff;
    background: #090c16;
    border: 1px solid rgba(255, 215, 0, .4);
    border-radius: 8px;
}
.customer-order-disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 600px) {
    .customer-order-panel { padding: 17px; }
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
                @else
                    Организация пока не настроила приём онлайн-заказов.
                @endif
            </div>

            <a class="customer-order-link" href="{{ route('customer.cart') }}">
                🛒 Корзина{{ $cartCount ? ' · ' . $cartCount : '' }}
            </a>
        </div>
    </div>

    @forelse($sections as $section)
        <section class="customer-order-section">
            <h2>{{ $section->name }}</h2>

            <div class="customer-order-grid">
                @forelse($section->activeItems as $item)
                    <article class="customer-order-item">
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
                                <input
                                    type="number"
                                    name="quantity"
                                    value="1"
                                    min="1"
                                    max="99"
                                    aria-label="Количество"
                                >
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
        </section>
    @empty
        <div class="customer-order-panel customer-order-muted">
            Меню пока пустое.
        </div>
    @endforelse
</div>
@endsection
