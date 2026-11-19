@if(auth('employee')->check())
    @php
        $adminNavigationMenuActive = request()->routeIs('employee.organization.menu.*');
        $adminNavigationOrdersActive = request()->routeIs('employee.organization.admin.orders.*');
    @endphp

    <style>
        .admin-section-navigation {
            position: sticky;
            top: 8px;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 7px;
            width: min(1180px, calc(100% - 28px));
            margin: 18px auto 18px;
            padding: 8px;
            overflow-x: auto;
            scrollbar-width: thin;
            background: rgba(7, 13, 24, .94);
            border: 1px solid rgba(0, 229, 212, .32);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .3);
            backdrop-filter: blur(14px);
        }
        .admin-section-navigation a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1 0 auto;
            min-height: 43px;
            padding: 0 18px;
            color: #aebdca;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: .9rem;
            font-weight: 650;
            text-decoration: none;
            white-space: nowrap;
            transition: .18s ease;
        }
        .admin-section-navigation a span {
            color: #00e5d4;
            font-size: 1.08rem;
        }
        .admin-section-navigation a:hover {
            color: #fff;
            background: rgba(0, 229, 212, .07);
            border-color: rgba(0, 229, 212, .22);
        }
        .admin-section-navigation a.is-active {
            color: #f5d76e;
            background: linear-gradient(135deg, rgba(0, 229, 212, .12), rgba(245, 207, 103, .10));
            border-color: rgba(245, 207, 103, .48);
            box-shadow: inset 0 0 14px rgba(0, 229, 212, .06), 0 0 12px rgba(245, 207, 103, .06);
        }
        @media(max-width:700px) {
            .admin-section-navigation {
                top: 4px;
                width: calc(100% - 16px);
                gap: 5px;
                margin: 12px auto 14px;
                padding: 6px;
                border-radius: 12px;
            }
            .admin-section-navigation a {
                min-height: 40px;
                padding: 0 13px;
                font-size: .82rem;
            }
        }
    </style>

    <nav class="admin-section-navigation" aria-label="Разделы администратора">
        <a href="{{ route('employee.organization.menu.index', $organization) }}" class="{{ $adminNavigationMenuActive ? 'is-active' : '' }}" @if($adminNavigationMenuActive) aria-current="page" @endif>
            <span>☷</span> Меню
        </a>
        <a href="{{ route('employee.organization.admin.orders.index', $organization) }}" class="{{ $adminNavigationOrdersActive ? 'is-active' : '' }}" @if($adminNavigationOrdersActive) aria-current="page" @endif>
            <span>▤</span> Заказы
        </a>
    </nav>
@endif
