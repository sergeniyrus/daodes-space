@if(auth('employee')->check())
    @php
        $adminNavigationMenuActive = request()->routeIs('employee.organization.menu.*');
        $adminNavigationOrdersActive = request()->routeIs('employee.organization.admin.orders.*');
        $adminNavigationFeedbackActive = request()->routeIs('employee.organization.admin.feedback.*');
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
            margin: 0 0 20px;
            padding: 8px;
            box-sizing: border-box;
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
            font: inherit;
            font-size: .88rem;
            font-weight: 650;
            text-decoration: none;
            white-space: nowrap;
            transition: .18s ease;
        }
        .organization-tab span { color: #00e5d4; font-size: 1.05rem; }
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
        @media(max-width:700px) {
            .organization-tabs { top: 4px; gap: 5px; margin-bottom: 14px; padding: 6px; border-radius: 12px; }
            .organization-tab { min-height: 40px; padding: 0 12px; font-size: .8rem; }
        }
    </style>

    <nav class="organization-tabs" aria-label="Разделы администратора" data-organization-tabs>
        <a href="{{ route('employee.organization.admin.orders.index', $organization) }}" class="organization-tab {{ $adminNavigationOrdersActive ? 'is-active' : '' }}" @if($adminNavigationOrdersActive) aria-current="page" @endif>
            <span>▤</span> Заказы
        </a>
        <a href="{{ route('employee.organization.admin.feedback.index', $organization) }}" class="organization-tab {{ $adminNavigationFeedbackActive ? 'is-active' : '' }}" @if($adminNavigationFeedbackActive) aria-current="page" @endif>
            <span>☆</span> Отзывы
        </a>
        <a href="{{ route('employee.organization.menu.index', $organization) }}" class="organization-tab {{ $adminNavigationMenuActive ? 'is-active' : '' }}" @if($adminNavigationMenuActive) aria-current="page" @endif>
            <span>☷</span> Меню
        </a>
    </nav>
@endif
