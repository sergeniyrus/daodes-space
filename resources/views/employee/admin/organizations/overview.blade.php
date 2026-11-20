@extends('organizations.layouts.management')

@section('title_page')
    Обзор — {{ $organization->name }}
@endsection

@section('management-content')
<style>
.admin-overview{color:#f3f5fa}
.admin-overview-section{margin:0 0 22px;padding:20px;background:linear-gradient(145deg,rgba(18,29,44,.94),rgba(8,14,25,.94));border:1px solid rgba(0,229,212,.24);border-radius:16px}
.admin-overview-title{display:flex;align-items:center;gap:10px;margin:0 0 16px;color:#f5d76e;font-size:1.2rem;font-weight:750}
.admin-overview-title:after{content:"";height:1px;flex:1;background:linear-gradient(90deg,rgba(0,229,212,.35),transparent)}
.admin-overview-subtitle{margin:-8px 0 16px;color:#9eacbd;font-size:.88rem}
.admin-overview-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.admin-overview-stat{min-width:0;padding:16px;border:1px solid rgba(0,229,212,.2);border-radius:13px;background:rgba(0,0,0,.2)}
.admin-overview-stat small{display:block;margin-bottom:8px;color:#aeb9c8;font-size:.82rem}
.admin-overview-stat strong{display:block;color:#00e5d4;font-size:1.7rem;line-height:1.2;font-variant-numeric:tabular-nums}
.admin-overview-stat.gold{border-color:rgba(245,215,110,.28)}
.admin-overview-stat.gold strong{color:#f5d76e}
.admin-overview-money{margin-top:12px;padding:15px 17px;border:1px solid rgba(245,215,110,.25);border-radius:13px;background:rgba(245,215,110,.045);color:#aeb9c8}
.admin-overview-money strong{float:right;color:#f5d76e;font-size:1.15rem}
.admin-team-list{display:grid;gap:10px}
.admin-team-member{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;border:1px solid rgba(0,229,212,.18);border-radius:12px;background:rgba(0,0,0,.18)}
.admin-team-main{min-width:0}
.admin-team-name{color:#f4f5fa;font-weight:700;overflow-wrap:anywhere}
.admin-team-phone{margin-top:4px;color:#9eacbd;font-size:.86rem}
.admin-team-meta{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:7px}
.admin-team-badge{display:inline-flex;align-items:center;padding:5px 9px;border:1px solid rgba(0,229,212,.22);border-radius:20px;color:#9dece5;background:rgba(0,229,212,.06);font-size:.76rem;white-space:nowrap}
.admin-team-badge.gold{border-color:rgba(245,215,110,.28);color:#f5d76e;background:rgba(245,215,110,.06)}
.admin-team-empty{padding:20px;text-align:center;color:#9eacbd;border:1px dashed rgba(0,229,212,.2);border-radius:12px}
@media(max-width:850px){.admin-overview-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.admin-overview-section{padding:14px}.admin-overview-grid{gap:8px}.admin-overview-stat{padding:12px}.admin-overview-stat strong{font-size:1.35rem}.admin-team-member{align-items:flex-start;flex-direction:column}.admin-team-meta{justify-content:flex-start}}
</style>

<div class="admin-overview">
    <section class="admin-overview-section" id="overview">
        <h1 class="admin-overview-title">Обзор организации</h1>
        <p class="admin-overview-subtitle">Операционная сводка за сегодня и текущая загрузка. Доступ только для просмотра.</p>

        <div class="admin-overview-grid">
            <div class="admin-overview-stat gold">
                <small>Заказов сегодня</small>
                <strong>{{ number_format((int) $overviewStats['orders_today'], 0, ',', ' ') }}</strong>
            </div>
            <div class="admin-overview-stat">
                <small>Новые</small>
                <strong>{{ number_format((int) $overviewStats['new_orders'], 0, ',', ' ') }}</strong>
            </div>
            <div class="admin-overview-stat">
                <small>На кухне</small>
                <strong>{{ number_format((int) $overviewStats['kitchen_orders'], 0, ',', ' ') }}</strong>
            </div>
            <div class="admin-overview-stat">
                <small>Готовы</small>
                <strong>{{ number_format((int) $overviewStats['ready_orders'], 0, ',', ' ') }}</strong>
            </div>
            <div class="admin-overview-stat">
                <small>У курьеров / в пути</small>
                <strong>{{ number_format((int) $overviewStats['delivery_orders'], 0, ',', ' ') }}</strong>
            </div>
            <div class="admin-overview-stat">
                <small>Завершены сегодня</small>
                <strong>{{ number_format((int) $overviewStats['completed_today'], 0, ',', ' ') }}</strong>
            </div>
            <div class="admin-overview-stat">
                <small>Отменены сегодня</small>
                <strong>{{ number_format((int) $overviewStats['cancelled_today'], 0, ',', ' ') }}</strong>
            </div>
        </div>

        <div class="admin-overview-money">
            Получено оплат сегодня
            <strong>{{ number_format((float) $paidToday, 2, ',', ' ') }} {{ $orderSettings?->currency ?? 'EUR' }}</strong>
            <div style="clear:both"></div>
        </div>

        <section style="margin-top:18px">
            <h2 class="admin-overview-title">История действий</h2>
            @if($activityLogs->isEmpty())
                <div class="admin-team-empty">Пока нет записей об изменениях организации.</div>
            @else
                <div class="admin-team-list">
                    @foreach($activityLogs as $activity)
                        <article class="admin-team-member" style="display:block">
                            <div class="admin-team-name">{{ $activity->description }}</div>
                            <div class="admin-team-phone">
                                {{ $activity->actor?->name ?? 'Система' }}
                                · {{ $activity->created_at?->timezone($organization->orderSettings?->timezoneName() ?? 'Asia/Krasnoyarsk')->format('d.m.Y H:i') }}
                            </div>
                            @if($activity->before_values || $activity->after_values)
                                <details style="margin-top:9px;color:#9eacbd">
                                    <summary>Посмотреть изменения</summary>
                                    <pre style="white-space:pre-wrap;overflow:auto">{{ json_encode(['до' => $activity->before_values, 'после' => $activity->after_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </section>


</div>

@endsection
