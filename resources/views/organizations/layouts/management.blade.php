@extends('template')

@section('main')
<style>
.organization-container{
    width:calc(100% - 32px);
    max-width:1180px;
    box-sizing:border-box;
    margin:22px auto 42px;
    padding:clamp(14px,2vw,28px);
    color:#f4f5fa;
    overflow:visible;
    background:
        radial-gradient(ellipse at 92% 0%,rgba(255,215,0,.09),transparent 34%),
        radial-gradient(ellipse at 0% 100%,rgba(0,229,212,.08),transparent 38%),
        linear-gradient(145deg,#080d18 0%,#101827 48%,#080d18 100%);
    border:1px solid rgba(0,229,212,.48);
    border-radius:22px;
    box-shadow:0 16px 48px rgba(0,0,0,.48),inset 0 1px 0 rgba(255,215,0,.14);
}
.organization-header{
    position:relative;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    margin-bottom:20px;
    padding:5px 5px 18px;
    border-bottom:1px solid rgba(0,229,212,.28);
}
.organization-header:after{
    content:"";
    position:absolute;
    left:18%;
    right:18%;
    bottom:-1px;
    height:2px;
    background:linear-gradient(90deg,transparent,#00e5d4,#ffd700,#00e5d4,transparent);
    box-shadow:0 0 12px rgba(0,229,212,.2);
}
.organization-header-main{display:flex;align-items:baseline;gap:16px;min-width:0}
.organization-management-title{color:#9aa6b6;font-size:.84rem;white-space:nowrap}
.organization-name{color:#f5cf67;font-size:clamp(1.35rem,3vw,1.8rem);font-weight:750;line-height:1.2;overflow-wrap:anywhere;text-shadow:0 0 12px rgba(255,215,0,.2)}
.organization-header-status{display:inline-flex;align-items:center;gap:8px;flex-shrink:0;color:#aeb5c4;font-size:.86rem}
.organization-status-indicator{width:9px;height:9px;flex-shrink:0;border-radius:50%;background:#4caf50;box-shadow:0 0 7px #4caf50}
.organization-layout-label{color:#00e5d4;font-size:.82rem;font-weight:650;letter-spacing:.04em}
.organization-layout-alert{padding:11px 14px;margin:0 0 14px;border:1px solid rgba(0,229,212,.25);border-radius:11px;background:rgba(0,229,212,.045);color:#dce8ef}
.organization-layout-alert.success{color:#6ee7b7;border-color:rgba(76,175,80,.4);background:rgba(76,175,80,.07)}
.organization-layout-alert.error{color:#ff9999;border-color:rgba(255,107,107,.4);background:rgba(255,107,107,.07)}
.organization-layout-content{min-width:0}
@media(max-width:700px){
    .organization-container{width:calc(100% - 12px);margin:10px auto 24px;padding:11px;border-radius:15px}
    .organization-header{display:block;text-align:center;padding:3px 3px 15px;margin-bottom:14px}
    .organization-header-main{display:block}
    .organization-management-title{margin-bottom:5px;font-size:.8rem}
    .organization-header-status{justify-content:center;margin-top:9px;font-size:.8rem}
    .organization-header:after{left:10%;right:10%}
}
</style>

<div class="organization-container">
    <header class="organization-header">
        <div class="organization-header-main">
            <div class="organization-management-title">
                @if(auth('employee')->check())
                    <span class="organization-layout-label">ПАНЕЛЬ АДМИНИСТРАТОРА</span>
                @else
                    {{ __('organizations.management') }}
                @endif
            </div>
            <div class="organization-name">{{ $organization->name }}</div>
        </div>
        @if(!auth('employee')->check())
            <div class="organization-header-status">
                <span class="organization-status-indicator"></span>
                <span>{{ __('organizations.status') }}: {{ __('organizations.statuses.' . $organization->status) }}</span>
            </div>
        @else
            <div class="organization-header-status">
                <span class="organization-status-indicator"></span>
                <span>Доступ администратора</span>
            </div>
        @endif
    </header>

    @if(auth('employee')->check())
        @include('employee.admin.partials.navigation', ['organization' => $organization])
    @else
        @include('organizations.partials.management-navigation', ['organization' => $organization])
    @endif

    @if(session('success'))
        <div class="organization-layout-alert success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="organization-layout-alert error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="organization-layout-alert error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <main class="organization-layout-content">
        @yield('management-content')
    </main>
</div>
@endsection
