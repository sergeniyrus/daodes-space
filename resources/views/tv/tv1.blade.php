
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

        html,
        body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background-color: #0a1a1a;
            font-family: Arial, sans-serif;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* =====================================================
           ОСНОВНОЙ ЭКРАН
        ===================================================== */

        .tv-menu {
            width: 1920px;
            height: 1080px;
            position: relative;
            background-image: url("{{ asset('images/tv1.png') }}");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            transform-origin: center center;
        }

        @media (max-width: 1919px) {
            .tv-menu {
                transform: scale(calc(100vw / 1920));
            }
        }

        @media (max-height: 1079px) {
            .tv-menu {
                transform: scale(calc(100vh / 1080));
            }
        }

        .menu-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 1920px;
            height: 1080px;
        }

        .brand-placeholder {
            height: 180px;
        }

        /* =====================================================
           ШАПКА ТАБЛИЦЫ
        ===================================================== */

        .menu-header {
            display: grid;
            grid-template-columns: 900px 270px 270px;
            align-items: center;
            height: 60px;
            padding-left: 70px;
            gap: 0;
        }

        .header-pill {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 52px;
            border-radius: 28px;
            font-size: 25px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            white-space: nowrap;
        }

        .header-pill.standard {
            background: rgba(10, 55, 50, 0.94);
            border: 2px solid #4ecdc4;
            color: #4ecdc4;
            box-shadow: 0 0 12px rgba(78, 205, 196, 0.2);
        }

        .header-pill.large {
            background: rgba(60, 35, 10, 0.94);
            border: 2px solid #d4af37;
            color: #f5d58a;
            box-shadow: 0 0 12px rgba(212, 175, 55, 0.2);
        }

        /* =====================================================
           СПИСОК БЛЮД
        ===================================================== */

        .menu-items {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .menu-item {
            display: grid;

            /* Название | Вес | Цена | Вес | Цена */
            grid-template-columns: 900px 100px 170px 100px 170px;

            align-items: center;
            gap: 0;

            padding: 5px 0 5px 20px;
            margin: 5px 400px 0px 70px;

            border: 1px solid rgba(218, 183, 111, 0.55);
            border-radius: 12px;
            background: rgba(8, 22, 17, 0.72);

            min-height: 100px;
        }

        /* =====================================================
           НАЗВАНИЕ И ОПИСАНИЕ
        ===================================================== */

        .item-info {
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-right: 15px;
        }

        .item-name-row {
            display: flex;
            align-items: baseline;
            gap: 15px;
            flex-wrap: wrap;
        }

        .item-name {
            font-size: 34px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.5px;

            /* ЗОЛОТО */
            color: gold;

            text-shadow:
                0 2px 3px rgba(0, 0, 0, 0.9),
                0 0 10px rgba(212, 175, 55, 0.25);
        }

        .item-description {
            font-size: 22px;
            color: #fff;
            margin-top: 3px;
            line-height: 1.15;
            font-weight: 500;

            /* text-shadow: 0 2px 5px rgba(0, 0, 0, 0.9); */
        }

        /* =====================================================
           ВЕС — ОТДЕЛЬНАЯ КОЛОНКА ПЕРЕД ЦЕНОЙ
        ===================================================== */

        .weight-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            white-space: nowrap;
        }

        .item-weight {
            font-size: 36px;
            font-weight: 700;
            color: #928e86;

            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.9);
            white-space: nowrap;
        }

        /* =====================================================
           ЦЕНЫ
        ===================================================== */

        .price-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .price-value {
            font-size: 40px;
            font-weight: 900;
            color: gold;

            text-shadow:
                0 3px 8px rgba(0, 0, 0, 0.9),
                0 0 8px rgba(212, 175, 55, 0.2);

            white-space: nowrap;
        }

        .price-value.large {
            color: gold;

            text-shadow:
                0 3px 8px rgba(0, 0, 0, 0.9),
                0 0 10px rgba(255, 190, 80, 0.25);
        }

        /* =====================================================
           ПУСТОЕ МЕНЮ
        ===================================================== */

        .empty-menu {
            font-size: 32px;
            color: #f5e6b8;
            padding: 30px;
            text-align: center;
        }

    </style>
</head>

<body>

<div class="tv-menu">

    <main class="menu-content">

        <div class="brand-placeholder"></div>

        {{-- ШАПКА ТАБЛИЦЫ --}}

        <div class="menu-header">

            <div></div>

            <div class="header-pill standard">
                Стандарт
            </div>

            <div class="header-pill large">
                Больше мяса
            </div>

        </div>

        {{-- ГРУППИРОВКА БЛЮД --}}

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

        {{-- СПИСОК БЛЮД --}}

        <div class="menu-items">

            @forelse($grouped as $baseName => $group)

                @php
                    $source = $group['base'] ?? $group['large'];
                @endphp

                <article class="menu-item">

                    {{-- НАЗВАНИЕ И ОПИСАНИЕ --}}

                    <div class="item-info">

                        <div class="item-name-row">

                            <span class="item-name">
                                {{ $baseName }}
                            </span>

                        </div>

                        @if($source->description)

                            <div class="item-description">
                                {{ $source->description }}
                            </div>

                        @endif

                    </div>

                    {{-- СТАНДАРТ: ВЕС --}}

                    <div class="weight-cell">

                        @if($group['base'] && ($group['base']->weight || $group['base']->unit))

                            <span class="item-weight">
                                {{ $group['base']->weight }} {{ $group['base']->unit }}
                            </span>

                        @endif

                    </div>

                    {{-- СТАНДАРТ: ЦЕНА --}}

                    <div class="price-cell">

                        @if($group['base'])

                            <div class="price-value">
                                {{ number_format((float) $group['base']->price, 0, ',', ' ') }} ₽
                            </div>

                        @endif

                    </div>

                    {{-- БОЛЬШЕ МЯСА: СОБСТВЕННЫЙ ВЕС --}}

                    <div class="weight-cell">

                        @if($group['large'] && ($group['large']->weight || $group['large']->unit))

                            <span class="item-weight">
                                {{ $group['large']->weight }} {{ $group['large']->unit }}
                            </span>

                        @endif

                    </div>

                    {{-- БОЛЬШЕ МЯСА: СОБСТВЕННАЯ ЦЕНА --}}

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
