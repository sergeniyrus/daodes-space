
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ёлки-Иголки — TV1</title>

    <style>
        /* =========================================================
           ОБЩИЕ СТИЛИ
        ========================================================= */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #0a1a1a;
            font-family: Arial, sans-serif;
            color: #fff;
            -webkit-tap-highlight-color: transparent;
        }

        /* =========================================================
           ОСНОВНОЙ ЭКРАН TV
        ========================================================= */

        .tv-menu {
            width: 1920px;
            height: 1080px;
            position: relative;

            background-image: url("{{ asset('images/org/2/tv-backgrounds/tv1.png') }}");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;

            transform-origin: top left;
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

        /* =========================================================
           ШАПКА ТАБЛИЦЫ
        ========================================================= */

        .menu-header {
            display: grid;

            grid-template-columns: 880px 270px 270px;

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

        /* =========================================================
           СПИСОК БЛЮД — TV
        ========================================================= */

        .menu-items {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .menu-item {
            display: grid;

            /*
               Название и описание | Стандарт | Больше мяса
            */

            grid-template-columns: 880px 270px 270px;

            align-items: center;
            gap: 0;

            padding: 5px 0 5px 20px;
            margin: 5px 400px 0 70px;

            border: 1px solid rgba(218, 183, 111, 0.55);
            border-radius: 12px;

            background: rgba(8, 22, 17, 0.72);

            min-height: 100px;
        }

        /* =========================================================
           НАЗВАНИЕ И ОПИСАНИЕ
        ========================================================= */

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
        }

        /* =========================================================
           КНОПКИ ВАРИАНТОВ
           ВЕС + ЦЕНА
        ========================================================= */

        .menu-option {
            width: 100%;
            height: 100%;
            min-height: 70px;

            display: grid;

            grid-template-columns: 100px 170px;

            align-items: center;
            justify-content: center;

            padding: 0;

            border: none;
            border-radius: 8px;

            background: transparent;
            color: inherit;

            font-family: inherit;
            cursor: pointer;

            transition:
                background 0.15s ease,
                transform 0.15s ease,
                box-shadow 0.15s ease;
        }

        /* =========================================================
           ВЕС
        ========================================================= */

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

        /* =========================================================
           ЦЕНА
        ========================================================= */

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

        /* =========================================================
           ЭФФЕКТ НАЖАТИЯ
        ========================================================= */

        .menu-option:active {
            transform: scale(0.96);

            background: rgba(212, 175, 55, 0.18);

            box-shadow:
                inset 0 0 15px rgba(212, 175, 55, 0.25);
        }

        .menu-option:focus-visible {
            outline: 2px solid #d4af37;
            outline-offset: 2px;
        }

        /* =========================================================
           ПУСТОЕ МЕНЮ
        ========================================================= */

        .empty-menu {
            font-size: 32px;
            color: #f5e6b8;

            padding: 30px;
            text-align: center;
        }

        /* =========================================================
           ПЛАНШЕТЫ И НЕБОЛЬШИЕ МОНИТОРЫ
        ========================================================= */

        @media (min-width: 901px) and (max-width: 1200px) {

            .menu-item {
                margin-right: 70px;
            }
        }

        /* =========================================================
           МОБИЛЬНЫЕ УСТРОЙСТВА
        ========================================================= */

        @media (max-width: 900px) {

            html,
            body {
                width: 100%;
                height: auto;
                min-height: 100%;

                overflow-x: hidden;
                overflow-y: auto;

                display: block;
            }

            .tv-menu {
                width: 100%;
                height: auto;
                min-height: 100vh;

                transform: none !important;

                background-size: cover;
                background-position: center top;
                background-attachment: fixed;
            }

            .menu-content {
                position: relative;

                width: 100%;
                height: auto;
                min-height: 100vh;

                padding-bottom: 25px;
            }

            .brand-placeholder {
                height: 90px;
            }

            /* Шапка */

            .menu-header {
                display: grid;

                grid-template-columns: 1fr 1fr;

                width: 100%;
                height: auto;

                gap: 10px;

                padding: 0 12px 15px;
            }

            .menu-header > div:first-child {
                display: none;
            }

            .header-pill {
                height: 42px;

                font-size: clamp(13px, 3.5vw, 20px);
                letter-spacing: 1px;

                border-radius: 22px;
            }

            /* Карточки */

            .menu-items {
                width: 100%;
                gap: 0;
            }

            .menu-item {
                display: grid;

                grid-template-columns: 1fr 1fr;

                gap: 8px;

                width: auto;
                min-height: 0;

                margin: 0 12px 12px;
                padding: 12px;

                border: 1px solid rgba(218, 183, 111, 0.55);
                border-radius: 12px;

                background: rgba(8, 22, 17, 0.88);
            }

            /* Название */

            .item-info {
                grid-column: 1 / -1;

                padding: 0 0 8px;
            }

            .item-name-row {
                gap: 5px;
            }

            .item-name {
                font-size: clamp(19px, 5vw, 27px);
                letter-spacing: 0.5px;
                line-height: 1.2;
            }

            .item-description {
                font-size: clamp(14px, 3.5vw, 18px);

                line-height: 1.25;
                margin-top: 4px;
            }

            /* Кнопки вариантов */

            .menu-option {
                display: flex;
                flex-direction: column;

                justify-content: center;
                align-items: stretch;

                min-width: 0;
                min-height: 76px;

                padding: 9px 8px;

                border: 1px solid rgba(218, 183, 111, 0.4);
                border-radius: 9px;

                background: rgba(20, 42, 33, 0.85);

                cursor: pointer;

                user-select: none;
                -webkit-user-select: none;

                touch-action: manipulation;
            }

            /* Подписи */

            .menu-item > .menu-option:nth-child(2)::before {
                content: "СТАНДАРТ";

                color: #4ecdc4;

                font-size: 11px;
                font-weight: 900;
                letter-spacing: 1px;

                text-align: center;
                margin-bottom: 7px;
            }

            .menu-item > .menu-option:nth-child(3)::before {
                content: "БОЛЬШЕ МЯСА";

                color: #f5d58a;

                font-size: 11px;
                font-weight: 900;
                letter-spacing: 1px;

                text-align: center;
                margin-bottom: 7px;
            }

            /* Вес */

            .weight-cell {
                width: 100%;
                height: auto;

                justify-content: center;
                margin-bottom: 3px;
            }

            .item-weight {
                font-size: clamp(16px, 4vw, 22px);
                color: #c9b98e;
            }

            /* Цена */

            .price-cell {
                width: 100%;
                height: auto;

                justify-content: center;
            }

            .price-value {
                font-size: clamp(20px, 5vw, 27px);
            }

            .price-value.large {
                color: #ffc66d;
            }

            /* Нажатие */

            .menu-option:active {
                transform: scale(0.95);

                background: rgba(212, 175, 55, 0.22);

                border-color: #d4af37;

                box-shadow:
                    0 0 12px rgba(212, 175, 55, 0.3),
                    inset 0 0 12px rgba(212, 175, 55, 0.15);
            }

            .menu-option:focus-visible {
                outline: 2px solid #d4af37;
                outline-offset: 2px;
            }

            .empty-menu {
                font-size: 20px;
                padding: 25px 15px;
            }
        }

        /* =========================================================
           УЗКИЕ ТЕЛЕФОНЫ
        ========================================================= */

        @media (max-width: 380px) {

            .brand-placeholder {
                height: 65px;
            }

            .menu-header {
                gap: 6px;
                padding-left: 8px;
                padding-right: 8px;
            }

            .header-pill {
                font-size: 12px;
                letter-spacing: 0;
                height: 38px;
            }

            .menu-item {
                margin-left: 8px;
                margin-right: 8px;

                padding: 10px;
                gap: 6px;
            }

            .item-name {
                font-size: 19px;
            }

            .item-description {
                font-size: 14px;
            }

            .menu-option {
                min-height: 70px;
                padding: 7px 4px;
            }

            .menu-item > .menu-option:nth-child(2)::before,
            .menu-item > .menu-option:nth-child(3)::before {
                font-size: 10px;
                letter-spacing: 0;
            }

            .item-weight {
                font-size: 16px;
            }

            .price-value {
                font-size: 20px;
            }
        }

        /* =========================================================
           ШИРОКИЕ МОБИЛЬНЫЕ ЭКРАНЫ
        ========================================================= */

        @media (min-width: 600px) and (max-width: 900px) {

            .brand-placeholder {
                height: 110px;
            }

            .menu-item {
                padding: 16px;
                gap: 12px;
            }

            .item-name {
                font-size: 28px;
            }

            .item-description {
                font-size: 18px;
            }

            .menu-option {
                min-height: 90px;
            }

            .item-weight {
                font-size: 22px;
            }

            .price-value {
                font-size: 28px;
            }
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

                    {{-- СТАНДАРТ: ВЕС + ЦЕНА --}}

                    <button type="button"
                            class="menu-option"
                            @if(!$group['base']) disabled @endif>

                        <div class="weight-cell">

                            @if($group['base'] && ($group['base']->weight || $group['base']->unit))

                                <span class="item-weight">
                                    {{ $group['base']->weight }} {{ $group['base']->unit }}
                                </span>

                            @endif

                        </div>

                        <div class="price-cell">

                            @if($group['base'])

                                <div class="price-value">
                                    {{ number_format((float) $group['base']->price, 0, ',', ' ') }} ₽
                                </div>

                            @endif

                        </div>

                    </button>

                    {{-- БОЛЬШЕ МЯСА: ВЕС + ЦЕНА --}}

                    <button type="button"
                            class="menu-option"
                            @if(!$group['large']) disabled @endif>

                        <div class="weight-cell">

                            @if($group['large'] && ($group['large']->weight || $group['large']->unit))

                                <span class="item-weight">
                                    {{ $group['large']->weight }} {{ $group['large']->unit }}
                                </span>

                            @endif

                        </div>

                        <div class="price-cell">

                            @if($group['large'])

                                <div class="price-value large">
                                    {{ number_format((float) $group['large']->price, 0, ',', ' ') }} ₽
                                </div>

                            @endif

                        </div>

                    </button>

                </article>

            @empty

                <div class="empty-menu">
                    Блюда пока не добавлены
                </div>

            @endforelse

        </div>

    </main>

</div>

<script>
    (function () {

        const menu = document.querySelector('.tv-menu');

        function resizeMenu() {

            if (window.innerWidth <= 900) {
                menu.style.transform = 'none';
                return;
            }

            const scaleX = window.innerWidth / 1920;
            const scaleY = window.innerHeight / 1080;

            const scale = Math.min(scaleX, scaleY);

            menu.style.transform = `scale(${scale})`;
        }

        window.addEventListener('resize', resizeMenu);

        resizeMenu();

    })();
</script>

</body>
</html>
