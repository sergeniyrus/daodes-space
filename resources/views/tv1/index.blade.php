<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1920, initial-scale=1.0">
    <title>Ёлки-Иголки — TV1</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background-color: #0a1a1a;
            font-family: 'Arial', sans-serif;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tv-menu {
            width: 1920px;
            height: 1080px;
            position: relative;
            background-image: url("{{ asset('images/shawarma.jpeg') }}");
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            transform-origin: center center;
        }

        @media (max-width: 1919px) {
            .tv-menu { transform: scale(calc(100vw / 1920)); }
        }
        @media (max-height: 1079px) {
            .tv-menu { transform: scale(calc(100vh / 1080)); }
        }

        /* Расширяем область меню — используем больше ширины */
        .menu-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 1320px;   /* было 1140px */
            height: 1080px;
            padding: 0;
        }

        .brand-placeholder {
            height: 200px;
        }

        /* ===== Шапка таблицы ===== */
        .menu-header {
            display: grid;
            grid-template-columns: 970px 20px 200px 240px;
            gap: 10px;
            padding: 0 10px;
            align-items:flex-end;
            height: 45px;
        }

        .header-pill {
            text-align: center;
            padding: 12px 24px;
            border-radius: 28px;
            font-size: 22px;          /* было 14px */
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .header-pill.standard {
            background: rgba(20, 50, 50, 0.92);
            border: 2px solid #4ecdc4;
            color: #4ecdc4;
        }

        .header-pill.large {
            background: rgba(60, 30, 10, 0.92);
            border: 2px solid #d4af37;
            color: #f5e6b8;
        }

        /* ===== Таблица меню ===== */
        .menu-items {
            display: flex;
            flex-direction: column;
            gap: 0;
            padding: 0;
        }

        /* .menu-items-scroll {
            max-height: 6800px;        
            overflow-y: auto;
            scrollbar-width: none;
        }
        .menu-items-scroll::-webkit-scrollbar { display: none; } */

        .menu-item {
            display: grid;
            grid-template-columns: 900px 100px 100px 250px;
            gap: 0px;
            align-items: center;
                          /* было 68px */
            padding: 10px 0 0 70px;
        }

        .item-info {
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            padding-right: 5px;
        }

        .item-name-row {
            display: flex;
            align-items: baseline;
            gap: 25px;
            flex-wrap: wrap;
        }

        .item-name {
            font-size: 30px;           /* было 26px */
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #f5e6b8;
            text-shadow: 0 3px 10px rgba(0,0,0,0.85);
        }

        .item-weight {
            font-size: 30px;           /* было 22px */
            font-weight: 700;
            color: #c9c9c0;
            white-space: nowrap;
            text-shadow: 0 2px 6px rgba(0,0,0,0.7);
        }

        .item-description {
            font-size: 20px;           /* было 14px */
            color: #d4d0c4;
            margin-top: 4px;
            text-shadow: 0 2px 6px rgba(0,0,0,0.85);
            line-height: 1.25;
            font-weight: 500;
        }

        .price-cell {
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .price-value {
            font-size: 42px;           /* было 32px */
            font-weight: 900;
            color: #f5e6b8;
            text-shadow: 0 3px 10px rgba(0,0,0,0.85);
            white-space: nowrap;
        }

        .price-value.large {
            color: #ffd27a;
        }

        .empty-menu {
            font-size: 32px;
            color: #aaa;
            padding: 30px;
            text-align: center;
        }
    </style>
</head>

<body>

<div class="tv-menu">

    <main class="menu-content">

        <div class="brand-placeholder"></div>

        {{-- Шапка: название | пусто | Стандарт | Больше мяса --}}
        <div class="menu-header">
            <div>&nbsp;</div>
            <div>&nbsp;</div>
            <div class="header-pill standard">Стандарт</div>
            <div class="header-pill large">Больше&nbsp;мяса</div>
        </div>

        @php
            $grouped = collect();

            foreach ($menuItems as $item) {
                $name = $item->name;
                $isLarge = str_contains($name, '(больше мяса)');
                $baseName = trim(str_replace('(больше мяса)', '', $name));

                if (!$grouped->has($baseName)) {
                    $grouped->put($baseName, [
                        'base' => null,
                        'large' => null,
                    ]);
                }

                $group = $grouped->get($baseName);

                if ($isLarge) {
                    $group['large'] = $item;
                } else {
                    $group['base'] = $item;
                }

                $grouped->put($baseName, $group);
            }
        @endphp

        <div class="menu-items menu-items-scroll">

            @forelse($grouped as $baseName => $group)

                @php
                    $source = $group['base'] ?? $group['large'];
                @endphp

                <article class="menu-item">

                    <div class="item-info">
                        <div class="item-name-row">
                            <span class="item-name">{{ $baseName }}</span>
                            @if($source->weight || $source->unit)
                                <span class="item-weight">
                                    {{ $source->weight }} {{ $source->unit }}
                                </span>
                            @endif
                        </div>

                        @if($source->description)
                            <div class="item-description">
                                {{ $source->description }}
                            </div>
                        @endif
                    </div>

                    {{-- Пустая колонка-сдвиг --}}
                    <div></div>

                    <div class="price-cell">
                        @if($group['base'])
                            <div class="price-value">
                                {{ number_format((float) $group['base']->price, 0, ',', ' ') }} ₽
                            </div>
                        @endif
                    </div>

                    <div class="price-cell">
                        @if($group['large'])
                            <div class="price-value large">
                                {{ number_format((float) $group['large']->price, 0, ',', ' ') }} ₽
                            </div>
                        @endif
                    </div>

                </article>

            @empty

                <div class="empty-menu">
                    Блюда пока не добавлены
                </div>

            @endforelse

        </div>

    </main>

</div>

</body>
</html>