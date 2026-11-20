@extends('organizations.layouts.management')

@section('title_page')
    Интеграция iiko — {{ $organization->name }}
@endsection

@section('management-content')
@php
    $connectionStatus = $connection?->connection_status ?? 'not_connected';
    $statusLabels = [
        'not_connected' => 'Не подключено',
        'pending' => 'Ожидает проверки',
        'connected' => 'Подключено',
        'error' => 'Ошибка подключения',
    ];
@endphp

<style>
.iiko-page{--gold:#ffd700;--cyan:#00e5d4;--muted:#aeb8c8;color:#f4f6fb;width:100%;max-width:1120px;margin:0 auto}
.iiko-page *{box-sizing:border-box}
.iiko-shell{padding:clamp(16px,2.5vw,28px);border:1px solid rgba(255,215,0,.42);border-radius:22px;background:radial-gradient(circle at 100% 0,rgba(255,215,0,.07),transparent 30%),radial-gradient(circle at 0 100%,rgba(0,229,212,.045),transparent 32%),linear-gradient(145deg,#0b0c18,#111626 55%,#0b0c18);box-shadow:0 16px 48px rgba(0,0,0,.38);margin-bottom:18px}
.iiko-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;padding-bottom:18px;margin-bottom:20px;border-bottom:1px solid rgba(255,215,0,.2)}
.iiko-eyebrow{color:var(--cyan);font-size:.78rem;letter-spacing:.1em;text-transform:uppercase;margin:0 0 7px}
.iiko-page h1,.iiko-page h2,.iiko-page h3{color:var(--gold);margin-top:0}
.iiko-page h1{font-size:clamp(1.45rem,3vw,2rem);margin-bottom:6px}
.iiko-page h2{font-size:1.16rem;margin-bottom:8px}
.iiko-page p{color:var(--muted);line-height:1.55}
.iiko-status{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid rgba(0,229,212,.32);border-radius:999px;color:var(--cyan);background:rgba(0,229,212,.06);font-size:.84rem;font-weight:700;white-space:nowrap}
.iiko-status.error{color:#ff8d8d;border-color:rgba(255,100,100,.35);background:rgba(255,100,100,.06)}
.iiko-status.pending{color:var(--gold);border-color:rgba(255,215,0,.35);background:rgba(255,215,0,.06)}
.iiko-card{padding:18px;border:1px solid rgba(0,229,212,.22);border-radius:15px;background:linear-gradient(145deg,rgba(20,31,47,.82),rgba(12,18,31,.82));margin-top:14px}
.iiko-card.gold{border-color:rgba(255,215,0,.25)}
.iiko-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.iiko-field{min-width:0}
.iiko-field.full{grid-column:1/-1}
.iiko-field label{display:block;color:#c1c9d7;font-size:.86rem;margin-bottom:7px}
.iiko-page input,.iiko-page select{width:100%;min-height:44px;padding:10px 12px;border:1px solid #39495a;border-radius:10px;background:#0b1220;color:#f5f6fa;outline:none}
.iiko-page input:focus,.iiko-page select:focus{border-color:var(--cyan);box-shadow:0 0 0 3px rgba(0,229,212,.1)}
.iiko-help{font-size:.8rem;color:#8996a8!important;margin:7px 0 0}
.iiko-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}
.iiko-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:9px 15px;border:1px solid var(--cyan);border-radius:11px;background:rgba(0,229,212,.055);color:var(--cyan)!important;font-weight:700;text-decoration:none;cursor:pointer;transition:.18s}
.iiko-button:hover{background:var(--cyan);color:#071316!important;box-shadow:0 0 16px rgba(0,229,212,.17)}
.iiko-button.gold{border-color:var(--gold);color:var(--gold)!important;background:rgba(255,215,0,.045)}
.iiko-button.gold:hover{background:var(--gold);color:#171506!important}
.iiko-button.danger{border-color:#d86b75;color:#ff9ca4!important;background:rgba(216,107,117,.05)}
.iiko-button.danger:hover{background:#d86b75;color:#16090b!important}
.iiko-alert{padding:12px 14px;border:1px solid;border-radius:11px;margin:0 0 13px;line-height:1.45}
.iiko-alert.success{color:#76e1ad;border-color:rgba(90,210,145,.35);background:rgba(50,180,110,.07)}
.iiko-alert.error{color:#ff9ca4;border-color:rgba(255,100,100,.35);background:rgba(255,100,100,.06)}
.iiko-selected{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.iiko-selected>div{padding:13px;border:1px solid rgba(255,215,0,.18);border-radius:11px;background:rgba(5,10,20,.35);min-width:0}
.iiko-selected small{display:block;color:#91a0b3;margin-bottom:5px}
.iiko-selected strong{display:block;color:#f4d982;overflow-wrap:anywhere}
.iiko-note{padding:13px 15px;border-left:3px solid var(--cyan);background:rgba(0,229,212,.045);border-radius:0 10px 10px 0;color:#bdc8d8;font-size:.86rem;line-height:1.55}
@media(max-width:680px){.iiko-grid,.iiko-selected{grid-template-columns:1fr}.iiko-actions{display:grid;grid-template-columns:1fr}.iiko-button{width:100%}.iiko-shell{border-radius:15px}}
</style>

<div class="iiko-page">
    <div class="iiko-shell">
        <div class="iiko-head">
            <div>
                <p class="iiko-eyebrow">Интеграции организации</p>
                <h1>Подключение iiko</h1>
                <p style="margin:0">Связь онлайн-заказов DAODES с ресторанной системой {{ $organization->name }}.</p>
            </div>
            <span class="iiko-status {{ $connectionStatus === 'error' ? 'error' : ($connectionStatus === 'pending' ? 'pending' : '') }}">
                {{ $statusLabels[$connectionStatus] ?? $statusLabels['not_connected'] }}
            </span>
        </div>

        @if (session('success'))
            <div class="iiko-alert success">{{ session('success') }}</div>
        @endif

        @if ($errors->has('iiko'))
            <div class="iiko-alert error">{{ $errors->first('iiko') }}</div>
        @endif

        @if ($errors->any() && !$errors->has('iiko'))
            <div class="iiko-alert error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="iiko-card gold">
            <h2>1. Данные подключения</h2>
            <p>Введите API Key, созданный администратором ресторана в iikoWeb → Интеграции → API keys. Ключ хранится в базе DAODES в зашифрованном виде и никогда не показывается повторно.</p>

            <form method="POST" action="{{ route('organizations.iiko.credentials', $organization) }}">
                @csrf
                <div class="iiko-grid">
                    <div class="iiko-field full">
                        <label for="iiko_api_key">API Key ресторана</label>
                        <input id="iiko_api_key" name="api_key" type="password" autocomplete="new-password" minlength="10" maxlength="500" required placeholder="{{ $connection ? 'Введите новый ключ, чтобы заменить текущий' : 'Вставьте API Key из iikoWeb' }}">
                        <p class="iiko-help">
                            @if ($connection)
                                Ключ уже сохранён. Для безопасности его значение скрыто.
                            @else
                                Общие appId и clientSecret приложения DAODES настраиваются администратором сервера в .env.
                            @endif
                        </p>
                    </div>
                </div>
                <div class="iiko-actions">
                    <button class="iiko-button gold" type="submit">Сохранить API Key</button>
                </div>
            </form>
        </section>

        @if ($connection?->iiko_organization_id)
            <section class="iiko-card">
                <h2>Вебхуки iiko</h2>
                <p>Вебхук позволяет получать изменения заказов и уведомления об обновлении стоп-листа без постоянного опроса API. Для одной организации iiko используется единый URL DAODES. После настройки проверьте в iikoWeb фильтры событий: DeliveryOrderUpdate, DeliveryOrderError и StopListUpdate. TableOrderUpdate подключим после завершения сценария заказов в зале.</p>
                <div class="iiko-selected">
                    <div>
                        <small>Состояние</small>
                        <strong>{{ $connection->webhook_enabled ? 'Настроен' : 'Не настроен' }}</strong>
                    </div>
                    <div>
                        <small>Адрес приёма</small>
                        <strong style="font-size:.82rem">/api/integrations/iiko/webhook</strong>
                    </div>
                </div>
                <div class="iiko-actions">
                    <form method="POST" action="{{ route('organizations.iiko.webhook.configure', $organization) }}">
                        @csrf
                        <button class="iiko-button gold" type="submit">Настроить вебхук в iiko</button>
                    </form>
                    <form method="POST" action="{{ route('organizations.iiko.webhook.check', $organization) }}">
                        @csrf
                        <button class="iiko-button" type="submit">Проверить настройки вебхука</button>
                    </form>
                </div>
            </section>
        @endif

        @if ($connection)
            <section class="iiko-card">
                <h2>2. Проверка доступа iikoCloud</h2>
                <p>DAODES проверит ключ, получит временный токен и запросит список организаций, доступных этому ресторанному API Key.</p>
                <form method="POST" action="{{ route('organizations.iiko.organizations', $organization) }}">
                    @csrf
                    <button class="iiko-button" type="submit">Проверить подключение и загрузить организации</button>
                </form>
            </section>
        @endif

        @if (!empty($iikoOrganizations) && $iikoOrganizations->isNotEmpty())
            <section class="iiko-card">
                <h2>3. Выбор организации iiko</h2>
                <form method="POST" action="{{ route('organizations.iiko.terminal-groups', $organization) }}">
                    @csrf
                    <div class="iiko-field">
                        <label for="iiko_organization_id">Организация / ресторан</label>
                        <select id="iiko_organization_id" name="iiko_organization_id" required>
                            <option value="">Выберите ресторан</option>
                            @foreach ($iikoOrganizations as $iikoOrganization)
                                <option value="{{ $iikoOrganization['id'] }}" @selected(($selectedIikoOrganization['id'] ?? $connection->iiko_organization_id) === $iikoOrganization['id'])>
                                    {{ $iikoOrganization['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="iiko-actions">
                        <button class="iiko-button" type="submit">Загрузить терминальные группы</button>
                    </div>
                </form>
            </section>
        @endif

        @if (!empty($terminalGroups) && $terminalGroups->isNotEmpty())
            <section class="iiko-card">
                <h2>4. Выбор терминальной группы</h2>
                <p>Терминальная группа определяет кассовую зону, куда iiko будет направлять заказы.</p>
                <form method="POST" action="{{ route('organizations.iiko.select-terminal', $organization) }}">
                    @csrf
                    <input type="hidden" name="iiko_organization_id" value="{{ $selectedIikoOrganization['id'] }}">
                    <input type="hidden" name="iiko_organization_name" value="{{ $selectedIikoOrganization['name'] }}">
                    <div class="iiko-field">
                        <label for="terminal_group_id">Терминальная группа</label>
                        <select id="terminal_group_id" name="terminal_group_id" required>
                            <option value="">Выберите терминальную группу</option>
                            @foreach ($terminalGroups as $terminalGroup)
                                <option value="{{ $terminalGroup['id'] }}" @selected($connection->terminal_group_id === $terminalGroup['id'])>
                                    {{ $terminalGroup['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="iiko-actions">
                        <button class="iiko-button gold" type="submit">Сохранить выбор</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($connection?->terminal_group_id)
            <section class="iiko-card">
                <h2>Столы и заказы в заведении</h2>
                <p>Сопоставьте номера столов DAODES с идентификаторами столов iiko. Автоматическую передачу заказов в зале включим после проверки гостевых карт и оплаты.</p>
                <a class="iiko-button" href="{{ route('organizations.iiko.tables.index', $organization) }}">Настроить столы</a>
            </section>
        @endif

        @if ($connection?->iiko_organization_id)
            <section class="iiko-card">
                <h2>Способы оплаты</h2>
                <p>Получите платёжные типы ресторана и сопоставьте их с методами оплаты DAODES. Это подготовка финансовой синхронизации, а не автоматическое проведение платежей.</p>
                <form method="POST" action="{{ route('organizations.iiko.payments.discover', $organization) }}">
                    @csrf
                    <button class="iiko-button" type="submit">Загрузить способы оплаты iiko</button>
                </form>
            </section>
        @endif

        @if ($connection?->iiko_organization_id && $connection?->terminal_group_id)
            <section class="iiko-card">
                <h2>Состояние интеграции</h2>
                <div class="iiko-selected">
                    <div>
                        <small>Подключение DAODES ↔ iiko</small>
                        <strong>{{ $connection->is_enabled ? 'Активно' : 'Выключено' }}</strong>
                    </div>
                    <div>
                        <small>Передача заказов</small>
                        <strong>{{ $connection->order_sync_enabled ? 'Включена' : 'Пока выключена' }}</strong>
                    </div>
                </div>
                <p class="iiko-help">Передачу заказов включим после проверки сопоставления меню, платёжных правил и статусов. Само подключение API не отправляет заказы.</p>
            </section>

            <section class="iiko-card">
                <h2>Внешнее меню iiko</h2>
                <p>Следующий этап — загрузка меню, цен, фотографий и модификаторов из iiko с предварительным просмотром без изменения текущего каталога DAODES.</p>
                <a class="iiko-button" href="{{ route('organizations.iiko.menu.index', $organization) }}">Открыть синхронизацию меню</a>
            </section>

            <section class="iiko-card gold">
                <h2>Выбранная конфигурация</h2>
                <div class="iiko-selected">
                    <div>
                        <small>Организация iiko</small>
                        <strong>{{ $connection->iiko_organization_name ?: $connection->iiko_organization_id }}</strong>
                    </div>
                    <div>
                        <small>Терминальная группа</small>
                        <strong>{{ $connection->terminal_group_name ?: $connection->terminal_group_id }}</strong>
                    </div>
                    <div>
                        <small>Формат адреса iiko</small>
                        <strong>{{ $connection->address_format_type ?: 'Не определён — доставка не отправляется' }}</strong>
                    </div>
                </div>
                <p class="iiko-help">Выбор сохранён, но передача заказов и синхронизация меню пока выключены до завершения следующих этапов интеграции.</p>
            </section>
        @endif

        <section class="iiko-card">
            <h2>Что будет подключено дальше</h2>
            <div class="iiko-note">
                Следующие этапы: внешнее меню и модификаторы, стоп-листы, сопоставление блюд DAODES с iiko, тестовые заказы, статусы через вебхуки и платёжные типы. Сохранение API Key само по себе не включает передачу реальных заказов.
            </div>
        </section>

        @if ($connection)
            <section class="iiko-card">
                <h2>История проверок и синхронизации</h2>
                @if (($syncLogs ?? collect())->isNotEmpty())
                    <div style="display:grid;gap:8px">
                        @foreach ($syncLogs as $log)
                            <div style="padding:11px 13px;border:1px solid {{ $log->status === 'success' ? 'rgba(90,210,145,.25)' : 'rgba(255,100,100,.25)' }};border-radius:10px;background:rgba(5,10,20,.35)">
                                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                                    <strong style="color:{{ $log->status === 'success' ? '#76e1ad' : '#ff9ca4' }}">{{ $log->sync_type }} · {{ $log->status === 'success' ? 'Успешно' : 'Ошибка' }}</strong>
                                    <span class="iiko-help">{{ $log->created_at?->format('d.m.Y H:i:s') }}</span>
                                </div>
                                <div style="color:#c1c9d7;font-size:.86rem;margin-top:5px">{{ $log->message }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p>Записей пока нет. После проверки подключения и синхронизации здесь появится история операций.</p>
                @endif
            </section>
        @endif

        @if ($connection)
            <section class="iiko-card">
                <h2>Управление подключением</h2>
                <p>Удаление отключит интеграцию этой организации. Существующие заказы, меню и данные DAODES останутся без изменений.</p>
                <form method="POST" action="{{ route('organizations.iiko.disconnect', $organization) }}" onsubmit="return confirm('Удалить подключение iiko для этой организации?');">
                    @csrf
                    @method('DELETE')
                    <button class="iiko-button danger" type="submit">Удалить подключение iiko</button>
                </form>
            </section>
        @endif
    </div>
</div>
@endsection
