@php
    $managementLinks = [
        [
            'label' => 'Обзор',
            'icon' => '⌂',
            'url' => route('organizations.manage', $organization) . '#overview',
            'tab' => 'overview',
            'active' => request()->routeIs('organizations.manage'),
        ],
        [
            'label' => 'Команда',
            'icon' => '♙',
            'url' => route('organizations.manage', $organization) . '#team',
            'tab' => 'team',
            'active' => false,
        ],
        [
            'label' => 'Заказы',
            'icon' => '▤',
            'url' => route('organizations.manage', $organization) . '#orders',
            'tab' => 'orders',
            'active' => request()->routeIs('organizations.orders.*'),
        ],
        [
            'label' => 'Отзывы',
            'icon' => '☆',
            'url' => route('organizations.feedback.index', $organization),
            'active' => request()->routeIs('organizations.feedback.*'),
        ],
        [
            'label' => 'Меню',
            'icon' => '☷',
            'url' => route('organizations.menu.index', $organization),
            'active' => request()->routeIs('organizations.menu.*'),
        ],
        [
            'label' => 'TV-Экраны',
            'icon' => '▣',
            'url' => route('organizations.manage', $organization) . '#tv',
            'tab' => 'tv',
            'active' => false,
        ],
        [
            'label' => 'Настройки',
            'icon' => '⚙',
            'url' => route('organizations.manage', $organization) . '#settings',
            'tab' => 'settings',
            'active' => false,
        ],
    ];
@endphp

<style>
.organization-tabs {
    position: sticky;
    top: 8px;
    z-index: 100;
    display: flex;
    align-items: center;
    gap: 7px;
    width: 100%;
    max-width: none;
    margin: 0 0 20px;
    padding: 8px;
    overflow-x: auto;
    scrollbar-width: thin;
    background: rgba(7, 13, 24, .94);
    border: 1px solid rgba(0, 229, 212, .32);
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .3);
    backdrop-filter: blur(14px);
}
.organization-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    flex: 1 0 auto;
    min-height: 42px;
    padding: 0 15px;
    color: #aebdca;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 10px;
    text-decoration: none;
    font: inherit;
    font-size: .88rem;
    font-weight: 650;
    white-space: nowrap;
    cursor: pointer;
    transition: .18s ease;
}
.organization-tab span {
    color: #00e5d4;
    font-size: 1.05rem;
}
.organization-tab:hover {
    color: #fff;
    background: rgba(0, 229, 212, .07);
    border-color: rgba(0, 229, 212, .22);
}
.organization-tab.is-active {
    color: #f5d76e;
    background: linear-gradient(135deg, rgba(0, 229, 212, .12), rgba(245, 207, 103, .10));
    border-color: rgba(245, 207, 103, .48);
    box-shadow: inset 0 0 14px rgba(0, 229, 212, .06), 0 0 12px rgba(245, 207, 103, .06);
}
@media (max-width: 700px) {
    .organization-tabs {
        top: 4px;
        width: 100%;
        gap: 5px;
        margin: 0 0 14px;
        padding: 6px;
        border-radius: 12px;
    }
    .organization-tab {
        min-height: 40px;
        padding: 0 12px;
        font-size: .8rem;
    }
}
</style>

<nav class="organization-tabs" aria-label="Разделы управления организацией" data-organization-tabs>
    @foreach($managementLinks as $link)
        <a
            href="{{ $link['url'] }}"
            class="organization-tab {{ $link['active'] ? 'is-active' : '' }}"
            @if(isset($link['tab'])) data-tab="{{ $link['tab'] }}" @endif
            @if($link['active']) aria-current="page" @endif
        ><span>{{ $link['icon'] }}</span> {{ $link['label'] }}</a>
    @endforeach
</nav>