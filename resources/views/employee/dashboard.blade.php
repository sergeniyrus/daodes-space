@extends('template')

@section('title_page')
    Рабочий кабинет
@endsection

@section('main')

<style>
.employee-dashboard {
    max-width: 1000px;
    margin: 35px auto 50px;
    padding: 25px;

    background:
        radial-gradient(circle at top right, rgba(255, 215, 0, 0.06), transparent 35%),
        radial-gradient(circle at bottom left, rgba(0, 229, 255, 0.04), transparent 35%),
        linear-gradient(145deg, #0b0c18 0%, #111323 50%, #0b0c18 100%);

    border: 1px solid rgba(255, 215, 0, 0.65);
    border-radius: 22px;
    color: #fff;
    box-shadow: 0 14px 45px rgba(0, 0, 0, 0.5);
}

.employee-dashboard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding-bottom: 18px;
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255, 215, 0, 0.2);
}

.employee-dashboard-title {
    margin: 0;
    color: gold;
    font-size: 1.6rem;
}

.employee-dashboard-name {
    margin-top: 6px;
    color: #aaa;
}

.employee-dashboard-logout {
    padding: 9px 16px;
    background: transparent;
    color: #ff7777;
    border: 1px solid #ff7777;
    border-radius: 18px;
    cursor: pointer;
}

.employee-dashboard-logout:hover {
    background: #ff7777;
    color: #0b0c18;
}

.employee-organizations {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 13px;
}

.employee-organization-card {
    display: block;
    padding: 18px;
    background: linear-gradient(145deg, rgba(255,255,255,0.035), rgba(255,255,255,0.01));
    border: 1px solid rgba(0, 229, 255, 0.35);
    border-radius: 15px;
    color: #fff;
    text-decoration: none;
    transition: 0.2s;
}

.employee-organization-card:hover {
    border-color: gold;
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.13);
    transform: translateY(-2px);
}

.employee-organization-name {
    color: gold;
    font-size: 1.1rem;
    font-weight: 700;
}

.employee-organization-role {
    margin-top: 9px;
    color: #00e5ff;
    font-size: 0.85rem;
}

.employee-dashboard-empty {
    padding: 25px 10px;
    color: #aaa;
    text-align: center;
}

@media (max-width: 600px) {
    .employee-dashboard {
        margin: 20px 10px 35px;
        padding: 16px;
        border-radius: 17px;
    }

    .employee-dashboard-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .employee-dashboard-title {
        font-size: 1.35rem;
    }
}
</style>

<div class="employee-dashboard">

    <div class="employee-dashboard-header">
        <div>
            <h1 class="employee-dashboard-title">
                Рабочий кабинет
            </h1>

            <div class="employee-dashboard-name">
                {{ $employee->name }} · {{ $employee->phone }}
            </div>
        </div>

        <form method="POST" action="{{ route('employee.logout') }}">
            @csrf

            <button type="submit" class="employee-dashboard-logout">
                Выйти
            </button>
        </form>
    </div>

    <h2 class="employee-dashboard-title" style="font-size: 1.1rem; margin-bottom: 15px;">
        Мои организации
    </h2>

    @if ($memberships->isNotEmpty())
        <div class="employee-organizations">
            @foreach ($memberships as $membership)
                @if(in_array($membership->role, ['cook', 'courier'], true))
                    <a
                        href="{{ route('employee.organization.orders.index', $membership->organization) }}"
                        class="employee-organization-card"
                    >
                @elseif($membership->role === 'admin')
                    <a
                        href="{{ route('employee.organization.menu.index', $membership->organization) }}"
                        class="employee-organization-card"
                    >
                @else
                    <div class="employee-organization-card">
                @endif
                    <div class="employee-organization-name">
                        {{ $membership->organization->name }}
                    </div>

                    <div class="employee-organization-role">
                        @switch($membership->role)
                            @case('cook')
                                👨‍🍳 Повар
                                @break

                            @case('courier')
                                🚚 Курьер
                                @break

                            @case('admin')
                                🛡 Администратор
                                @break

                            @default
                                👤 Сотрудник
                        @endswitch
                    </div>
                @if(in_array($membership->role, ['cook', 'courier', 'admin'], true))
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <div class="employee-dashboard-empty">
            Пока нет организаций с активным доступом.
            Обратитесь к менеджеру организации.
        </div>
    @endif

</div>

@endsection