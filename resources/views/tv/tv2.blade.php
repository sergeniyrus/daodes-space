
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ёлки-Иголки — TV2</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            width: 100%;
            height: 100%;
            font-family: Arial, Helvetica, sans-serif;
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

            background: url('/images/tv2.png?v=2') center / cover no-repeat;
        }

        .tv-content {
            width: 100%;
            height: 100%;
            padding: 30px 30px;

            display: flex;
            flex-direction: column;
        }

        /* MAIN COLUMNS */

        .menu-columns {
            flex: 1;
            min-height: 0;

            display: grid;

            grid-template-columns:
                minmax(0, calc((100% - 36px) / 3 + 200px))
                minmax(0, calc((100% - 36px) / 3 - 100px))
                minmax(0, calc((100% - 36px) / 3 - 100px));

            gap: 18px;
        }

        /* COLUMN LAYOUT */

        .menu-column {
            min-width: 0;
            min-height: 0;
        }

        .left-column {
            display: flex;
            flex-direction: column;
        }

        /* CENTRAL COLUMN — 70 / 30 */

        .middle-column {
            display: flex;
    flex-direction: column;
            grid-template-rows: 7fr 3fr;
            gap: 10px;
            overflow: hidden;
        }

        /* RIGHT COLUMN — 45 / 55 */

        .right-column {
            display: flex;
    flex-direction: column;
            grid-template-rows: 60fr 40fr;
            gap: 10px;
            overflow: hidden;
        }

        /* CATEGORY */

        .menu-section {
            min-width: 0;
            min-height: 0;

            display: flex;
            flex-direction: column;

            padding: 14px;

            border: 1px solid rgba(218, 183, 111, 0.35);
            border-radius: 15px;

            background: rgba(8, 22, 17, 0.72);

            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3);

            overflow: hidden;
        }

        /* CATEGORY HEADER */

        .section-header {
            flex-shrink: 0;

            margin-bottom: 14px;
            padding: 5px 5px 12px;

            border-bottom: 1px solid rgba(218, 183, 111, 0.4);
        }

        .section-title {
            margin: 0;

            font-size: clamp(20px, 1.55vw, 30px);
            line-height: 1.2;

            font-weight: 900;

            color: #ffd27a;
            text-transform: uppercase;
        }

        /* CATEGORY ITEMS */

        .section-items {
            flex: 1;
            min-height: 0;

            display: flex;
            flex-direction: column;
            align-items: stretch;

            gap: 8px;

            overflow: hidden;
        }

        /* MENU CARD */

        .menu-card {
            flex: 0 0 auto;

            min-width: 0;
            min-height: 0;

            display: flex;
            align-items: stretch;

            padding: 9px 12px;

            border: 1px solid rgba(218, 183, 111, 0.22);
            border-radius: 10px;

            background: rgba(15, 28, 23, 0.92);

            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.22);

            overflow: hidden;
        }

        .item-info {
            width: 100%;
            min-width: 0;

            display: flex;
            flex-direction: column;
        }

        /* DISH HEADING */

        .item-heading {
            flex-shrink: 0;

            display: flex;
            align-items: center;

            gap: 8px;
            min-width: 0;

            margin-bottom: 5px;
        }

        .item-name {
            min-width: 0;

            font-size: clamp(15px, 1.05vw, 22px);
            line-height: 1.2;
            font-weight: 900;

            color: #76d8fb;
        }

        .item-heading .item-name {
            flex: 1 1 auto;

            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* WEIGHT */

        .item-inline-weight {
            flex-shrink: 0;

            font-size: clamp(12px, 0.78vw, 15px);
            font-weight: 700;

            color: #a8cfc2;
            white-space: nowrap;
        }

        /* PRICE */

        .item-inline-price {
            flex-shrink: 0;

            font-size: clamp(16px, 1.1vw, 23px);
            font-weight: 900;

            color: #ffd27a;
            white-space: nowrap;
        }

        /* DESCRIPTION */

        .item-description {
            margin-bottom: 5px;

            font-size: clamp(12px, 0.78vw, 15px);
            line-height: 1.35;

            color: #b8c9c0;

            overflow: hidden;

            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        /* VARIANTS */

        .item-variants {
            margin-top: auto;

            display: flex;
            flex-direction: column;

            gap: 3px;
        }

        .variant-row {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 10px;
            min-width: 0;
        }

        .item-weight {
            min-width: 0;

            font-size: clamp(12px, 0.78vw, 15px);
            font-weight: 700;

            color: #a8cfc2;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-price {
            flex-shrink: 0;

            font-size: clamp(16px, 1.1vw, 23px);
            font-weight: 900;

            color: #ffd27a;

            white-space: nowrap;
        }

        .currency {
            font-size: 0.75em;
        }

        /* COMPACT PRODUCTS */

        .menu-card.compact-card {
            padding: 7px 12px;
            min-height: 0;
        }

        .compact-card .item-info {
            justify-content: center;
        }

        .compact-card .item-heading {
            margin-bottom: 0;
        }

        /* SETS */

        .menu-card.set-card {
            padding: 7px 12px;
        }

        .set-card .item-heading {
            margin-bottom: 3px;
        }

        .set-card .item-name {
            flex: 1 1 auto;
        }

        .set-card .item-inline-weight,
        .set-card .item-inline-price {
            flex-shrink: 0;
        }

        /* SAUCES — COMPACT */

        .middle-column .menu-section:last-child .section-header {
            margin-bottom: 8px;
            padding-bottom: 7px;
        }

        .middle-column .menu-section:last-child .section-title {
            font-size: clamp(17px, 1.2vw, 23px);
        }

        .middle-column .menu-section:last-child .section-items {
            gap: 5px;
        }

        .middle-column .menu-section:last-child .menu-card {
            padding: 5px 9px;
        }

        .middle-column .menu-section:last-child .item-name {
            font-size: clamp(13px, 0.9vw, 18px);
        }

        .middle-column .menu-section:last-child .item-inline-price,
        .middle-column .menu-section:last-child .item-price {
            font-size: clamp(14px, 0.95vw, 19px);
        }

        /* EMPTY MENU */

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

        /* RESPONSIVE */

        @media (max-width: 1200px) {

            .tv-content {
                padding: 25px;
            }

            .menu-columns {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
            }

            .middle-column,
            .right-column {
                gap: 10px;
            }

            .menu-section {
                padding: 10px;
            }

            .menu-card {
                padding: 8px 10px;
            }

            .item-name {
                font-size: 15px;
            }

            .item-price,
            .item-inline-price {
                font-size: 16px;
            }

            .item-inline-weight,
            .item-weight {
                font-size: 12px;
            }
        }

        @media (max-width: 800px) {

            html,
            body {
                height: auto;
                overflow: auto;
            }

            .tv-screen {
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }

            .tv-content {
                padding: 15px;
            }

            .menu-columns {
                grid-template-columns: 1fr;
            }

            .menu-section {
                min-height: auto;
            }

            .section-items {
                overflow: visible;
            }

            .middle-column {
                grid-template-rows: auto auto;
                overflow: visible;
            }

            .right-column {
                grid-template-rows: auto auto;
                overflow: visible;
            }

            .menu-card {
                min-height: 0;
            }
        }

    </style>
</head>

<body>

    @php
        /*
         * Группируем строки menu_items в карточки блюд.
         * Одинаковые названия и варианты с количеством "N шт." попадают
         * в одну карточку, а вес/цена остаются отдельными вариантами.
         */
        $buildSectionItems = function ($sectionId) use ($menuItems) {
            return $menuItems
                ->where('menu_section_id', $sectionId)
                ->groupBy(function ($item) use ($sectionId) {
                    $name = trim($item->name);

                    if ($sectionId === 14) {
                        $name = preg_replace('/^Соус\s+/ui', '', $name);
                    }

                    $name = preg_replace('/\s+\d+\s*шт\.?$/ui', '', $name);

                    return mb_strtolower(trim($name));
                })
                ->map(function ($variants) use ($sectionId) {
                    $firstVariant = $variants->first();
                    $name = trim($firstVariant->name);

                    if ($sectionId === 14) {
                        $name = preg_replace('/^Соус\s+/ui', '', $name);
                    }

                    $name = preg_replace('/\s+\d+\s*шт\.?$/ui', '', $name);

                    $description = $variants->first(function ($variant) {
                        return !empty($variant->description);
                    });

                    return (object) [
                        'name' => trim($name),
                        'description' => $description ? $description->description : null,
                        'variants' => $variants->values(),
                    ];
                })
                ->values();
        };

        $menuSections = [
            10 => [
                'id' => 10,
                'title' => 'Наборы и закуски',
                'items' => $buildSectionItems(10),
            ],
            15 => [
                'id' => 15,
                'title' => 'Горячие закуски',
                'items' => $buildSectionItems(15),
            ],
            14 => [
                'id' => 14,
                'title' => 'Соусы',
                'items' => $buildSectionItems(14),
            ],
            16 => [
                'id' => 16,
                'title' => 'Блины, сырники и вафли',
                'items' => $buildSectionItems(16),
            ],
            17 => [
                'id' => 17,
                'title' => 'Сэндвичи',
                'items' => $buildSectionItems(17),
            ],
        ];

        $menuColumns = [
            [
                'class' => 'left-column',
                'sections' => [10],
            ],
            [
                'class' => 'middle-column',
                'sections' => [15, 14],
            ],
            [
                'class' => 'right-column',
                'sections' => [16, 17],
            ],
        ];
    @endphp

    <div class="tv-screen">

        <div class="tv-content">

            <main class="menu-columns">

                @foreach ($menuColumns as $column)

                    <div class="menu-column {{ $column['class'] }}">

                        @foreach ($column['sections'] as $sectionId)

                            @php
                                $section = $menuSections[$sectionId];
                            @endphp

                            <section class="menu-section">

                                {{-- CATEGORY HEADER --}}

                                <header class="section-header">
                                    <h2 class="section-title">
                                        {{ $section['title'] }}
                                    </h2>
                                </header>

                                {{-- CATEGORY ITEMS --}}

                                <div class="section-items">

                                    @forelse ($section['items'] as $item)

                                        @php
                                            $isSet = preg_match(
                                                '/набор\s*№?\s*[1-5]/ui',
                                                $item->name
                                            );

                                            $isSmallSnack = preg_match(
                                                '/арахис|фисташк|гренк|сухарик/ui',
                                                $item->name
                                            );

                                            $hasDescription = !empty($item->description);
                                            $singleVariant = $item->variants->count() === 1;
                                            $firstVariant = $item->variants->first();
                                            $compactCard = !$hasDescription && $singleVariant;
                                        @endphp

                                        <article class="menu-card
                                            {{ $compactCard ? 'compact-card' : '' }}
                                            {{ $isSet ? 'set-card' : '' }}
                                            {{ $isSmallSnack ? 'compact-card' : '' }}">

                                            <div class="item-info">

                                                {{-- NAME + WEIGHT + PRICE --}}

                                                @if ($isSet || ($compactCard && !$hasDescription))

                                                    <div class="item-heading">

                                                        <div class="item-name">
                                                            {{ $item->name }}
                                                        </div>

                                                        @if ($singleVariant)

                                                            <span class="item-inline-weight">
                                                                {{ $firstVariant->weight }}
                                                                {{ $firstVariant->unit }}

                                                                @if (preg_match('/\s+(\d+)\s*шт\.?$/ui', $firstVariant->name, $matches))
                                                                    · {{ $matches[1] }} шт.
                                                                @endif
                                                            </span>

                                                            <span class="item-inline-price">
                                                                {{ number_format((float) $firstVariant->price, 0, ',', ' ') }}
                                                                <span class="currency">₽</span>
                                                            </span>

                                                        @endif

                                                    </div>

                                                @else

                                                    <div class="item-heading">
                                                        <div class="item-name">
                                                            {{ $item->name }}
                                                        </div>
                                                    </div>

                                                @endif

                                                {{-- DESCRIPTION --}}

                                                @if ($hasDescription)
                                                    <div class="item-description">
                                                        {{ $item->description }}
                                                    </div>
                                                @endif

                                                {{-- VARIANTS --}}

                                                @if (!$compactCard && (!$isSet || !$singleVariant))

                                                    <div class="item-variants">

                                                        @foreach ($item->variants as $variant)

                                                            <div class="variant-row">

                                                                <span class="item-weight">
                                                                    {{ $variant->weight }}
                                                                    {{ $variant->unit }}

                                                                    @if (preg_match('/\s+(\d+)\s*шт\.?$/ui', $variant->name, $matches))
                                                                        · {{ $matches[1] }} шт.
                                                                    @endif
                                                                </span>

                                                                <span class="item-price">
                                                                    {{ number_format((float) $variant->price, 0, ',', ' ') }}
                                                                    <span class="currency">₽</span>
                                                                </span>

                                                            </div>

                                                        @endforeach

                                                    </div>

                                                @endif

                                            </div>

                                        </article>

                                    @empty

                                        <div class="menu-empty">
                                            Раздел пока пуст
                                        </div>

                                    @endforelse

                                </div>

                            </section>

                        @endforeach

                    </div>

                @endforeach

            </main>

        </div>

    </div>

</body>

</html>
