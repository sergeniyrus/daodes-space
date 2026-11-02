
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Меню — TV2</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            width: 100%;
            min-height: 100%;
            font-family: Arial, Helvetica, sans-serif;
            /* background: #101714; */
            color: #fff;
            overflow: hidden;
        }

        body {
            min-height: 100vh;
        }

        /* SCREEN */

        .tv-screen {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 600px;
            overflow: hidden;

            background:url('/images/tv2.png') center / cover no-repeat;
        }

        .tv-content {
            width: 100%;
            height: 100%;
            padding: 35px 55px 25px;

            display: flex;
            flex-direction: column;
        }

        /* HEADER */

        .tv-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 25px;
            flex-shrink: 0;
        }

        .tv-title {
            margin: 0;

            font-size: clamp(28px, 2.4vw, 48px);
            font-weight: 900;
            letter-spacing: 2px;

            color: #ffd27a;
            text-transform: uppercase;
        }

        .tv-subtitle {
            margin-top: 7px;

            font-size: clamp(14px, 1vw, 19px);
            color: #b8c9c0;
            letter-spacing: 2px;
        }

        .tv-logo {
            font-size: clamp(20px, 1.5vw, 30px);
            font-weight: 900;

            color: #d7b56d;
            letter-spacing: 3px;
        }

        /* MENU */

        .menu-pages {
            position: relative;
            flex: 1;
            min-height: 0;
        }

        .menu-page {
            position: absolute;
            inset: 0;

            display: none;

            grid-template-columns:
                repeat(var(--columns), minmax(0, 1fr));

            grid-template-rows:
                repeat(var(--rows), minmax(0, 1fr));

            grid-auto-flow: column;

            gap: 12px;
        }

        .menu-page.active {
            display: grid;
        }

        /* MENU CARD */

        .menu-card {
            min-width: 0;
            min-height: 0;

            display: flex;
            align-items: center;

            gap: 12px;
            padding: 10px;

            border: 1px solid rgba(218, 183, 111, 0.25);
            border-radius: 12px;

            background: rgba(15, 28, 23, 0.88);

            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.25);

            overflow: hidden;
        }

        /* IMAGE */

        .item-image {
            flex: 0 0 82px;

            width: 82px;
            height: 82px;

            border-radius: 9px;
            overflow: hidden;

            background: rgba(255, 255, 255, 0.06);

            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .item-image img {
            display: block;

            width: 100%;
            height: 100%;

            object-fit: cover;
        }

        .image-placeholder {
            width: 100%;
            height: 100%;

            display: flex;
            align-items: center;
            justify-content: center;

            text-align: center;

            font-size: 11px;
            line-height: 1.4;

            color: #83958c;
        }

        /* DISH INFO */

        .item-info {
            min-width: 0;
            flex: 1;

            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .item-name {
            font-size: clamp(13px, 0.95vw, 18px);
            line-height: 1.15;
            font-weight: 900;

            color: #f3e6ca;

            margin-bottom: 4px;

            overflow-wrap: anywhere;
        }

        .item-description {
            font-size: 11px;
            line-height: 1.2;

            color: #9eb0a6;

            margin-bottom: 4px;

            overflow: hidden;

            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        /* VARIANTS */

        .item-variants {
            display: flex;
            flex-direction: column;

            gap: 3px;
            margin-top: 3px;
        }

        .variant-row {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 8px;
            min-width: 0;
        }

        .item-weight {
            font-size: clamp(11px, 0.75vw, 14px);
            font-weight: 700;

            color: #91b9ad;

            white-space: nowrap;
        }

        .item-price {
            font-size: clamp(15px, 1.1vw, 21px);
            font-weight: 900;

            color: #ffd27a;

            white-space: nowrap;
        }

        .currency {
            font-size: 0.75em;
        }

        /* EMPTY */

        .menu-empty {
            width: 100%;
            height: 100%;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 30px;
            font-weight: 700;

            color: #ffd27a;
        }

        /* FOOTER */

        .tv-footer {
            flex-shrink: 0;

            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-top: 18px;
            padding-top: 12px;

            border-top: 1px solid rgba(218, 183, 111, 0.25);

            color: #9eb0a6;
            font-size: 14px;
        }

        .page-indicator {
            color: #ffd27a;
            font-weight: 700;
        }

        /* RESPONSIVE */

        @media (max-width: 1200px) {

            .tv-content {
                padding: 25px;
            }

            .menu-page {
                gap: 8px;
            }

            .item-image {
                flex-basis: 65px;
                width: 65px;
                height: 65px;
            }

            .menu-card {
                gap: 8px;
                padding: 7px;
            }
        }

        @media (max-width: 800px) {

            html,
            body {
                overflow: auto;
            }

            .tv-screen {
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }

            .tv-content {
                padding: 18px;
            }

            .menu-pages {
                flex: none;
            }

            .menu-page,
            .menu-page.active {
                position: relative;

                display: grid;

                grid-template-columns: repeat(2, minmax(0, 1fr));
                grid-template-rows: auto;

                grid-auto-flow: row;

                gap: 8px;
            }

            .menu-page:not(.active) {
                display: none;
            }

            .menu-card {
                min-height: 105px;
            }

            .tv-footer {
                margin-top: 15px;
            }
        }

        @media (max-width: 480px) {

            .menu-page,
            .menu-page.active {
                grid-template-columns: 1fr;
            }

            .tv-header {
                align-items: flex-start;
                gap: 10px;
            }

            .tv-logo {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>

@php

    /*
    |--------------------------------------------------------------------------
    | ГРУППИРОВКА ТОВАРОВ
    |--------------------------------------------------------------------------
    | Наггетсы куриные 5 шт -> Наггетсы куриные
    | Наггетсы куриные 9 шт -> Наггетсы куриные
    |
    | Вес, количество штук и цена остаются отдельными вариантами.
    */

    $groupedItems = $menuItems
        ->groupBy(function ($item) {

            $name = trim($item->name);

            $name = preg_replace(
                '/\s+\d+\s*шт\.?$/ui',
                '',
                $name
            );

            return mb_strtolower(trim($name));
        })
        ->map(function ($items) {

            $first = $items->first();

            $descriptionItem = $items->first(function ($item) {
                return !empty($item->description);
            });

            $displayName = preg_replace(
                '/\s+\d+\s*шт\.?$/ui',
                '',
                trim($first->name)
            );

            return (object) [
                'name' => $displayName,
                'description' => $descriptionItem?->description,
                'variants' => $items->sortBy('id')->values(),
            ];
        })
        ->values();


    /*
    |--------------------------------------------------------------------------
    | АВТОМАТИЧЕСКОЕ РАЗДЕЛЕНИЕ НА СТРАНИЦЫ
    |--------------------------------------------------------------------------
    */

    $maxColumns = 4;
    $maxRows = 7;

    $maxItemsPerPage = $maxColumns * $maxRows;

    $menuPages = $groupedItems->chunk($maxItemsPerPage);

    $totalPages = $menuPages->count();


    /*
    |--------------------------------------------------------------------------
    | АВТОМАТИЧЕСКИЙ РАСЧЁТ СТОЛБЦОВ И СТРОК
    |--------------------------------------------------------------------------
    */

    $pageLayouts = [];

    foreach ($menuPages as $pageIndex => $pageItems) {

        $count = $pageItems->count();

        /*
         * Подбираем наиболее компактную сетку.
         *
         * При равном количестве строк предпочитаем
         * сетку с большим количеством столбцов,
         * чтобы карточки были шире.
         */

        $bestColumns = 1;
        $bestRows = $count;

        $bestDifference = PHP_INT_MAX;

        for ($columns = 1; $columns <= $maxColumns; $columns++) {

            $rows = (int) ceil($count / $columns);

            if ($rows > $maxRows) {
                continue;
            }

            $difference = abs($columns - $rows);

            if (
                $difference < $bestDifference ||
                (
                    $difference === $bestDifference &&
                    $columns > $bestColumns
                )
            ) {

                $bestDifference = $difference;

                $bestColumns = $columns;
                $bestRows = $rows;
            }
        }

        $pageLayouts[$pageIndex] = [
            'columns' => $bestColumns,
            'rows' => $bestRows,
            'count' => $count,
        ];
    }

@endphp


<div class="tv-screen">

    <div class="tv-content">

        <!-- HEADER -->

        <header class="tv-header">

            <div>

                <h1 class="tv-title">
                    Меню
                </h1>

                <div class="tv-subtitle">
                    ЗАКУСКИ И БЛЮДА
                </div>

            </div>

            <div class="tv-logo">
                DAODES
            </div>

        </header>


        <!-- MENU -->

        <main class="menu-pages">

            @forelse($menuPages as $pageIndex => $pageItems)

                <section
                    class="menu-page {{ $pageIndex === 0 ? 'active' : '' }}"
                    data-page="{{ $pageIndex }}"
                    style="
                        --columns: {{ $pageLayouts[$pageIndex]['columns'] }};
                        --rows: {{ $pageLayouts[$pageIndex]['rows'] }};
                    "
                >

                    @foreach($pageItems as $item)

                        <article class="menu-card">

                            <!-- IMAGE -->

                            <div class="item-image">

                                <div class="image-placeholder">
                                    Фото<br>блюда
                                </div>

                            </div>


                            <!-- INFO -->

                            <div class="item-info">

                                <div class="item-name">
                                    {{ $item->name }}
                                </div>

                                @if($item->description)

                                    <div class="item-description">
                                        {{ $item->description }}
                                    </div>

                                @endif


                                <!-- VARIANTS -->

                                <div class="item-variants">

                                    @foreach($item->variants as $variant)

                                        <div class="variant-row">

                                            <span class="item-weight">

                                                {{ $variant->weight }}
                                                {{ $variant->unit }}

                                                @if(preg_match('/\s+(\d+)\s*шт\.?$/ui', $variant->name, $matches))

                                                    · {{ $matches[1] }} шт.

                                                @endif

                                            </span>


                                            <span class="item-price">

                                                {{ number_format((float) $variant->price, 0, ',', ' ') }}

                                                <span class="currency">
                                                    ₽
                                                </span>

                                            </span>

                                        </div>

                                    @endforeach

                                </div>

                            </div>

                        </article>

                    @endforeach

                </section>

            @empty

                <div class="menu-empty">
                    Меню временно недоступно
                </div>

            @endforelse

        </main>


        <!-- FOOTER -->

        <footer class="tv-footer">

            <div>
                Приятного аппетита!
            </div>

            <div class="page-indicator">

                <span id="currentPage">1</span>

                /

                <span id="totalPages">
                    {{ $totalPages }}
                </span>

            </div>

        </footer>

    </div>

</div>


<!-- PAGE SWITCHING -->

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const pages = document.querySelectorAll('.menu-page');

        const currentPageElement = document.getElementById('currentPage');

        if (!pages.length) {
            return;
        }

        let currentPage = 0;

        const switchInterval = 12000;

        function showPage(index) {

            pages.forEach(function (page, pageIndex) {

                page.classList.toggle(
                    'active',
                    pageIndex === index
                );

            });

            currentPageElement.textContent = index + 1;

        }

        if (pages.length > 1) {

            setInterval(function () {

                currentPage++;

                if (currentPage >= pages.length) {
                    currentPage = 0;
                }

                showPage(currentPage);

            }, switchInterval);

        }

    });
</script>

</body>
</html>
