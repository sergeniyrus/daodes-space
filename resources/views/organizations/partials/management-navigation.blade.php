@php
    $managementLinks = [
        [
            'label' => 'Обзор',
            'url' => route('organizations.manage', $organization) . '#overview',
            'active' => request()->routeIs('organizations.manage'),
        ],
        [
            'label' => 'Команда',
            'url' => route('organizations.manage', $organization) . '#team',
            'active' => false,
        ],
        [
            'label' => 'Заказы',
            'url' => route('organizations.orders.index', $organization),
            'active' => request()->routeIs('organizations.orders.*'),
        ],
        [
            'label' => 'Отзывы',
            'url' => route('organizations.feedback.index', $organization),
            'active' => request()->routeIs('organizations.feedback.*'),
        ],
        [
            'label' => 'Меню',
            'url' => route('organizations.menu.index', $organization),
            'active' => request()->routeIs('organizations.menu.*'),
        ],
        [
            'label' => 'TV-Экраны',
            'url' => route('organizations.manage', $organization) . '#tv',
            'active' => false,
        ],
        [
            'label' => 'Настройки',
            'url' => route('organizations.manage', $organization) . '#settings',
            'active' => false,
        ],
    ];@endphp

<style>
.organization-management-navigation {
    display: flex;
    align-items: center;
    gap: 7px;
    width: min(1200px, calc(100% - 28px));
    margin: 18px auto 0;
    padding: 8px;
    overflow-x: auto;
    border: 1px solid rgba(0, 229, 212, .22);
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(11, 15, 29, .96), rgba(18, 25, 42, .94));
    scrollbar-width: thin;
}
.organization-management-navigation a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    min-height: 39px;
    padding: 0 15px;
    color: #b9c5d4;
    border: 1px solid transparent;
    border-radius: 10px;
    text-decoration: none;
    font-size: .88rem;
    font-weight: 650;
    white-space: nowrap;
    transition: .18s ease;
}
.organization-management-navigation a:hover {
    color: #00e5d4;
    border-color: rgba(0, 229, 212, .35);
    background: rgba(0, 229, 212, .07);
}
.organization-management-navigation a.is-active {
    color: #101522;
    border-color: #00e5d4;
    background: linear-gradient(110deg, #00e5d4, #b5f5e9);
    box-shadow: 0 4px 15px rgba(0, 229, 212, .14);
}
@media (max-width: 600px) {
    .organization-management-navigation {
        width: calc(100% - 20px);
        margin-top: 12px;
        padding: 6px;
    }
    .organization-management-navigation a {
        min-height: 36px;
        padding: 0 12px;
        font-size: .82rem;
    }
}
</style>

<nav class="organization-management-navigation" aria-label="Разделы управления организацией">
    @foreach($managementLinks as $link)
        <a href="{{ $link['url'] }}" class="{{ $link['active'] ? 'is-active' : '' }}" @if($link['active']) aria-current="page" @endif>
            {{ $link['label'] }}
        </a>
    @endforeach
</nav>
