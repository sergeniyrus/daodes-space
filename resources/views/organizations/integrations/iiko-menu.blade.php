@extends('organizations.layouts.management')

@section('title_page')
    Меню iiko — {{ $organization->name }}
@endsection

@section('management-content')
<style>
.iiko-menu-page{--gold:#ffd700;--cyan:#00e5d4;--muted:#aeb8c8;color:#f4f6fb;width:100%;max-width:1120px;margin:0 auto}
.iiko-menu-page *{box-sizing:border-box}
.iiko-menu-shell{padding:clamp(16px,2.5vw,28px);border:1px solid rgba(255,215,0,.42);border-radius:22px;background:radial-gradient(circle at 100% 0,rgba(255,215,0,.07),transparent 30%),radial-gradient(circle at 0 100%,rgba(0,229,212,.045),transparent 32%),linear-gradient(145deg,#0b0c18,#111626 55%,#0b0c18);box-shadow:0 16px 48px rgba(0,0,0,.38);margin-bottom:18px}
.iiko-menu-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;padding-bottom:17px;margin-bottom:18px;border-bottom:1px solid rgba(255,215,0,.2)}
.iiko-menu-page h1,.iiko-menu-page h2{color:var(--gold);margin-top:0}
.iiko-menu-page h1{font-size:clamp(1.4rem,3vw,1.9rem);margin-bottom:6px}
.iiko-menu-page h2{font-size:1.15rem}
.iiko-menu-page p{color:var(--muted);line-height:1.55}
.iiko-menu-card{padding:17px;border:1px solid rgba(0,229,212,.22);border-radius:14px;background:rgba(12,18,31,.76);margin-top:14px}
.iiko-menu-page label{display:block;color:#c1c9d7;font-size:.85rem;margin:0 0 7px}
.iiko-menu-page select{width:100%;min-height:43px;padding:9px 11px;border:1px solid #39495a;border-radius:10px;background:#0b1220;color:#f5f6fa}
.iiko-menu-button{display:inline-flex;align-items:center;justify-content:center;min-height:41px;padding:9px 15px;border:1px solid var(--cyan);border-radius:10px;background:rgba(0,229,212,.05);color:var(--cyan);font-weight:700;cursor:pointer;text-decoration:none}
.iiko-menu-button:hover{background:var(--cyan);color:#071316}
.iiko-menu-button.gold{border-color:var(--gold);color:var(--gold)}
.iiko-menu-button.gold:hover{background:var(--gold);color:#171506}
.iiko-menu-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:13px}
.iiko-menu-alert{padding:12px 14px;border:1px solid rgba(255,100,100,.35);border-radius:10px;color:#ff9ca4;background:rgba(255,100,100,.06);margin-bottom:12px}
.iiko-menu-table-wrap{overflow-x:auto}
.iiko-menu-table{width:100%;border-collapse:collapse;min-width:540px}
.iiko-menu-table th,.iiko-menu-table td{padding:10px 12px;border-bottom:1px solid rgba(0,229,212,.13);text-align:left;vertical-align:top}
.iiko-menu-table th{color:var(--cyan);font-size:.82rem;font-weight:700}
.iiko-menu-table td{color:#e4e9f1;font-size:.88rem}
.iiko-menu-muted{color:#8d9bad!important;font-size:.8rem}
.iiko-menu-stats{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}
.iiko-menu-stat{padding:10px 13px;border:1px solid rgba(255,215,0,.22);border-radius:10px;color:#f3d77c;background:rgba(255,215,0,.035)}
@media(max-width:650px){.iiko-menu-shell{border-radius:15px}.iiko-menu-actions{display:grid}.iiko-menu-button{width:100%}}
</style>

<div class="iiko-menu-page">
    <div class="iiko-menu-shell">
        <div class="iiko-menu-head">
            <div>
                <p style="color:var(--cyan);font-size:.78rem;letter-spacing:.1em;text-transform:uppercase;margin:0 0 7px">Интеграция iiko</p>
                <h1>Внешнее меню iiko</h1>
                <p style="margin:0">Загрузка каталога, проверка блюд и подготовка сопоставления с меню DAODES.</p>
            </div>
            <a class="iiko-menu-button" href="{{ route('organizations.iiko.index', $organization) }}">← Настройки iiko</a>
        </div>

        @if ($errors->has('iiko_menu'))
            <div class="iiko-menu-alert">{{ $errors->first('iiko_menu') }}</div>
        @endif

        @if (!$connection || !$connection->iiko_organization_id || !$connection->terminal_group_id)
            <div class="iiko-menu-card">
                <h2>Сначала подключите ресторан</h2>
                <p>Для получения внешнего меню необходимо сохранить API Key и выбрать организацию iiko вместе с терминальной группой.</p>
                <a class="iiko-menu-button gold" href="{{ route('organizations.iiko.index', $organization) }}">Перейти к подключению</a>
            </div>
        @else
            <div class="iiko-menu-card">
                <h2>1. Получить доступные внешние меню</h2>
                <p>iiko возвращает только те внешние меню, которые настроены для выбранной организации. Если список пустой, администратору ресторана нужно настроить внешнее меню в iikoWeb.</p>
                <form method="POST" action="{{ route('organizations.iiko.menu.discover', $organization) }}">
                    @csrf
                    <button class="iiko-menu-button" type="submit">Загрузить список внешних меню</button>
                </form>
            </div>

            @if ($externalMenus->isNotEmpty())
                <div class="iiko-menu-card">
                    <h2>2. Выбрать меню и ценовую категорию</h2>
                    <form method="POST" action="{{ route('organizations.iiko.menu.preview', $organization) }}">
                        @csrf
                        <div style="margin-bottom:13px">
                            <label for="external_menu_id">Внешнее меню</label>
                            <select id="external_menu_id" name="external_menu_id" required>
                                <option value="">Выберите меню</option>
                                @foreach ($externalMenus as $menu)
                                    <option value="{{ $menu['id'] }}" @selected($connection->external_menu_id === $menu['id'])>{{ $menu['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="price_category_id">Ценовая категория (необязательно)</label>
                            <select id="price_category_id" name="price_category_id">
                                <option value="">Цены по умолчанию</option>
                                @foreach ($externalMenus as $menu)
                                    @foreach ($menu['price_categories'] as $category)
                                        <option value="{{ $category['id'] }}">{{ $menu['name'] }} — {{ $category['name'] }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <p class="iiko-menu-muted">Если ресторан использует отдельную ценовую категорию для онлайн-заказов, выберите её. Иначе оставьте цены по умолчанию.</p>
                        </div>
                        <div class="iiko-menu-actions">
                            <button class="iiko-menu-button gold" type="submit">Загрузить меню для предварительного просмотра</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($menuPreview)
                <div class="iiko-menu-card">
                    <h2>3. Предварительный просмотр</h2>
                    <p>{{ $menuPreview['name'] }}</p>
                    <div class="iiko-menu-stats">
                        <div class="iiko-menu-stat">Показано позиций: {{ $menuPreview['total_items'] }}</div>
                        <div class="iiko-menu-stat">ID меню: {{ $menuPreview['external_menu_id'] }}</div>
                    </div>
                    <p class="iiko-menu-muted">Это безопасный просмотр данных iiko. Никакие блюда, цены или категории DAODES автоматически не изменены.</p>
                    @if ($previewItems->isNotEmpty())
                        <div class="iiko-menu-table-wrap">
                            <table class="iiko-menu-table">
                                <thead>
                                    <tr><th>Категория</th><th>Блюдо</th><th>Цена iiko</th><th>Модификаторы</th><th>Product ID</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($previewItems as $item)
                                        <tr>
                                            <td>{{ $item['group'] }}</td>
                                            <td>{{ $item['name'] }}</td>
                                            <td>{{ is_numeric($item['price']) ? number_format((float) $item['price'], 2, ',', ' ') : '—' }}</td>
                                            <td>{{ $item['has_modifiers'] ? 'Есть' : '—' }}</td>
                                            <td><span class="iiko-menu-muted">{{ $item['id'] }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p>Ответ iiko получен, но структура списка блюд не распознана автоматически. Данные не изменены; потребуется уточнить фактический формат меню ресторана.</p>
                    @endif
                </div>
            @endif
        @endif

        <div class="iiko-menu-card">
            <h2>Следующий шаг</h2>
            <p>После проверки структуры меню добавим сопоставление блюд DAODES ↔ iiko, размеров, модификаторов и цен. Автоматическую замену меню не включаем: каждое сопоставление должно быть подтверждено, а исходное меню DAODES сохранено.</p>
        </div>
    </div>
</div>
@endsection
