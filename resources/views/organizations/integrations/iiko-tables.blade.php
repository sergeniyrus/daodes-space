@extends('organizations.layouts.management')

@section('title_page')
    Столы iiko — {{ $organization->name }}
@endsection

@section('management-content')
<style>
.iiko-tables{max-width:1080px;margin:0 auto;color:#f4f6fb}
.iiko-tables-shell{padding:clamp(16px,2.5vw,26px);border:1px solid rgba(255,215,0,.38);border-radius:20px;background:linear-gradient(145deg,#0b0c18,#111626 55%,#0b0c18)}
.iiko-tables h1,.iiko-tables h2{color:#ffd700;margin-top:0}
.iiko-tables p{color:#aeb8c8;line-height:1.55}
.iiko-tables-card{padding:16px;border:1px solid rgba(0,229,212,.22);border-radius:13px;background:rgba(12,18,31,.78);margin-top:13px}
.iiko-tables-form{display:grid;grid-template-columns:minmax(130px,.8fr) minmax(200px,1.4fr) auto;gap:10px;align-items:end;padding:12px 0;border-bottom:1px solid rgba(0,229,212,.12)}
.iiko-tables label{display:block;color:#c1c9d7;font-size:.85rem;margin-bottom:6px}
.iiko-tables input,.iiko-tables select{width:100%;min-height:41px;padding:8px 10px;border:1px solid #39495a;border-radius:9px;background:#0b1220;color:#f5f6fa}
.iiko-tables button,.iiko-tables a{display:inline-flex;align-items:center;justify-content:center;min-height:41px;padding:8px 14px;border:1px solid #00e5d4;border-radius:10px;background:rgba(0,229,212,.05);color:#00e5d4;text-decoration:none;font-weight:700;cursor:pointer}
.iiko-tables button:hover,.iiko-tables a:hover{background:#00e5d4;color:#071316}
.iiko-tables .muted{font-size:.8rem;color:#91a0b3}
@media(max-width:720px){.iiko-tables-form{grid-template-columns:1fr}.iiko-tables button,.iiko-tables a{width:100%}}
</style>
<div class="iiko-tables">
    <div class="iiko-tables-shell">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1>Сопоставление столов</h1>
                <p>Связываем номер стола, который указывает гость в DAODES, с реальным tableId в iiko.</p>
            </div>
            <a href="{{ route('organizations.iiko.index', $organization) }}">← Настройки iiko</a>
        </div>

        @if (session('success'))
            <p style="color:#76e1ad">{{ session('success') }}</p>
        @endif
        @if ($errors->has('iiko_tables'))
            <p style="color:#ff9ca4">{{ $errors->first('iiko_tables') }}</p>
        @endif

        @if (!$connection || !$connection->terminal_group_id)
            <div class="iiko-tables-card">
                <p>Сначала выберите терминальную группу iiko.</p>
                <a href="{{ route('organizations.iiko.index', $organization) }}">Подключить iiko</a>
            </div>
        @else
            <div class="iiko-tables-card">
                <h2>Столы iiko</h2>
                <p>Загрузите доступные залы и столы из выбранной терминальной группы. Если стол временно не попал в список доступных для бронирования, повторите запрос позже или проверьте его состояние в iikoWeb.</p>
                <form method="POST" action="{{ route('organizations.iiko.tables.discover', $organization) }}">
                    @csrf
                    <button type="submit">Загрузить столы из iiko</button>
                </form>
            </div>

            @if ($restaurantTables->isNotEmpty())
                <div class="iiko-tables-card">
                    <h2>Сопоставление</h2>
                    @foreach ($restaurantTables as $table)
                        @php($existing = $tableMappings->first(fn ($mapping) => $mapping->iiko_table_id === $table['id']))
                        <form class="iiko-tables-form" method="POST" action="{{ route('organizations.iiko.tables.save', $organization) }}">
                            @csrf
                            <div>
                                <label>Номер стола DAODES</label>
                                <input name="daodes_table_number" required maxlength="64" value="{{ $existing?->daodes_table_number }}" placeholder="Например, 12">
                            </div>
                            <div>
                                <label>Стол iiko</label>
                                <input value="{{ $table['section_name'] }} — {{ $table['name'] }}" disabled>
                                <input type="hidden" name="iiko_table_id" value="{{ $table['id'] }}">
                            </div>
                            <button type="submit">Сохранить связь</button>
                        </form>
                    @endforeach
                </div>
            @endif

            @if ($tableMappings->isNotEmpty())
                <div class="iiko-tables-card">
                    <h2>Сохранённые сопоставления</h2>
                    @foreach ($tableMappings as $mapping)
                        <div style="display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid rgba(0,229,212,.12)">
                            <strong>DAODES: {{ $mapping->daodes_table_number }}</strong>
                            <span class="muted">{{ $mapping->restaurant_section_name }} — {{ $mapping->iiko_table_name }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="iiko-tables-card">
            <h2>Важно</h2>
            <p>Сопоставление столов подготавливает интеграцию заказов в зале. Автоматическая передача dine-in заказов останется выключенной до отдельной проверки идентификатора гостевой карты, сценария оплаты по счёту гостя и безопасной повторной отправки table order.</p>
        </div>
    </div>
</div>
@endsection
