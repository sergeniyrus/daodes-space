@extends('organizations.layouts.management')

@section('title_page')
    Команда — {{ $organization->name }}
@endsection

@section('management-content')
<style>
.admin-team-page{color:#f3f5fa}
.admin-team-section{padding:20px;background:linear-gradient(145deg,rgba(18,29,44,.94),rgba(8,14,25,.94));border:1px solid rgba(0,229,212,.24);border-radius:16px}
.admin-team-heading{display:flex;align-items:center;gap:10px;margin:0 0 8px;color:#f5d76e;font-size:1.2rem;font-weight:750}
.admin-team-heading:after{content:"";height:1px;flex:1;background:linear-gradient(90deg,rgba(0,229,212,.35),transparent)}
.admin-team-description{margin:0 0 18px;color:#9eacbd;font-size:.88rem;line-height:1.5}
.admin-team-list{display:grid;gap:10px}
.admin-team-member{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;border:1px solid rgba(0,229,212,.18);border-radius:12px;background:rgba(0,0,0,.18)}
.admin-team-main{min-width:0}
.admin-team-name{color:#f4f5fa;font-weight:700;overflow-wrap:anywhere}
.admin-team-phone{margin-top:4px;color:#9eacbd;font-size:.86rem}
.admin-team-meta{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:7px}
.admin-team-badge{display:inline-flex;align-items:center;padding:5px 9px;border:1px solid rgba(0,229,212,.22);border-radius:20px;color:#9dece5;background:rgba(0,229,212,.06);font-size:.76rem;white-space:nowrap}
.admin-team-badge.gold{border-color:rgba(245,215,110,.28);color:#f5d76e;background:rgba(245,215,110,.06)}
.admin-team-empty{padding:22px;text-align:center;color:#9eacbd;border:1px dashed rgba(0,229,212,.2);border-radius:12px}
@media(max-width:650px){.admin-team-section{padding:14px}.admin-team-member{align-items:flex-start;flex-direction:column}.admin-team-meta{justify-content:flex-start}}
</style>

<div class="admin-team-page">
    <section class="admin-team-section">
        <h1 class="admin-team-heading">Команда</h1>
        <p class="admin-team-description">Список сотрудников, их роли и текущий статус. Администратор может просматривать информацию, но не может менять роли, активировать, блокировать или удалять сотрудников.</p>

        <div class="admin-team-list">
            @forelse($staffMemberships as $staffMembership)
                @if($staffMembership->employee)
                    <article class="admin-team-member">
                        <div class="admin-team-main">
                            <div class="admin-team-name">{{ $staffMembership->employee->name }}</div>
                            <div class="admin-team-phone">{{ $staffMembership->employee->phone }}</div>
                        </div>
                        <div class="admin-team-meta">
                            <span class="admin-team-badge">
                                @switch($staffMembership->role)
                                    @case('admin') Администратор @break
                                    @case('cook') Повар @break
                                    @case('courier') Курьер @break
                                    @default Сотрудник
                                @endswitch
                            </span>
                            <span class="admin-team-badge {{ $staffMembership->status === 'active' ? 'gold' : '' }}">
                                @switch($staffMembership->status)
                                    @case('active') Активен @break
                                    @case('pending') Ожидает активации @break
                                    @case('blocked') Заблокирован @break
                                    @default {{ $staffMembership->status }}
                                @endswitch
                            </span>
                            <span class="admin-team-badge {{ $staffMembership->employee->phone_verified_at ? 'gold' : '' }}">
                                {{ $staffMembership->employee->phone_verified_at ? 'Телефон подтверждён' : 'Телефон не подтверждён' }}
                            </span>
                        </div>
                    </article>
                @endif
            @empty
                <div class="admin-team-empty">Сотрудники пока не добавлены.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
