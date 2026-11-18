
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ёлки-Иголки — TV3</title>

    <style>
        /* =========================================================
           ЕДИНАЯ СИСТЕМА СТИЛЕЙ И РАЗМЕРОВ
        ========================================================= */

        :root {
            --font-category: clamp(17px, 1.25vw, 24px);
            --font-name: clamp(15px, 1vw, 20px);
            --font-description: clamp(12px, 0.78vw, 16px);
            --font-meta: clamp(12px, 0.75vw, 15px);
            --font-price: clamp(15px, 0.95vw, 19px);

            --color-gold: #ffd27a;
            --color-turquoise: #76d8fb;
            --color-text: #b8c9c0;
            --color-muted: #a8cfc2;

            --section-background: rgba(8, 22, 17, 0.72);
            --card-background: rgba(15, 28, 23, 0.92);

            --section-padding: 14px;
            --card-padding: 7px 10px;
            --item-gap: 6px;
        }

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

        /* =========================================================
           SCREEN
        ========================================================= */

        .tv-screen {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 600px;
            overflow: hidden;

            background: url('/images/org/2/tv-backgrounds/tv3.png?v=2') center / cover no-repeat;
        }

        .tv-content {
            width: 100%;
            height: 100%;
            padding: 35px 50px;

            display: flex;
            flex-direction: column;
        }

        /* =========================================================
           MAIN COLUMNS
        ========================================================= */

        .menu-columns {
            flex: 1;
            min-height: 0;

            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 1fr)
                minmax(0, 1fr)
                minmax(0, 1fr);

            gap: 18px;
        }

        /* =========================================================
           COLUMN LAYOUT
        ========================================================= */

        .menu-column {
            min-width: 0;
            min-height: 0;
        }

        /* FIRST COLUMN — ВАРИМ САМИ + БЕЗАЛКОГОЛЬНЫЕ */

        .left-column {
            display: flex;
            flex-direction: column;

            gap: 18px;
            overflow: hidden;
        }

        /* SECOND COLUMN — COCKTAILS */

        .middle-column {
            display: flex;
            flex-direction: column;

            gap: 18px;
            overflow: hidden;
        }

        /* THIRD COLUMN — COFFEE + DESSERTS */

        .coffee-column {
            display: flex;
            flex-direction: column;

            gap: 18px;
            overflow: hidden;
        }

        /* FOURTH COLUMN — BEER */

        .beer-column {
            display: flex;
            flex-direction: column;

            gap: 18px;
            overflow: hidden;
        }

        /* =========================================================
           CATEGORY
        ========================================================= */

        .menu-section {
            min-width: 0;
            min-height: 0;

            display: flex;
            flex-direction: column;

            padding: var(--section-padding);

            border: 1px solid rgba(218, 183, 111, 0.35);
            border-radius: 15px;

            background: var(--section-background);

            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3);

            overflow: hidden;
        }

        /* CATEGORY HEADER */

        .section-header {
            flex-shrink: 0;

            margin-bottom: 12px;
            padding: 5px 5px 10px;

            border-bottom: 1px solid rgba(218, 183, 111, 0.4);
        }

        .section-title {
            margin: 0;

            font-size: var(--font-category);
            line-height: 1.2;
            font-weight: 900;

            color: var(--color-gold);
            text-transform: uppercase;
        }

        /* =========================================================
           CATEGORY ITEMS
        ========================================================= */

        .section-items {
            flex: 1;
            min-height: 0;

            display: flex;
            flex-direction: column;
            align-items: stretch;

            gap: var(--item-gap);

            overflow: hidden;
        }

        /* =========================================================
           MENU CARD
        ========================================================= */

        .menu-card {
            flex: 0 0 auto;

            min-width: 0;
            min-height: 0;

            display: flex;
            align-items: stretch;

            padding: var(--card-padding);

            border: 1px solid rgba(218, 183, 111, 0.22);
            border-radius: 10px;

            background: var(--card-background);

            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.22);

            overflow: hidden;
        }

        .item-info {
            width: 100%;
            min-width: 0;

            display: flex;
            flex-direction: column;
        }

        /* =========================================================
           ЕДИНОЕ НАЗВАНИЕ БЛЮДА
        ========================================================= */

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

            font-size: var(--font-name);
            line-height: 1.2;
            font-weight: 900;

            color: var(--color-turquoise);
        }

        .item-heading .item-name {
            flex: 1 1 auto;

            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* =========================================================
           VARIANTS
        ========================================================= */

        .item-variants {
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

            font-size: var(--font-meta);
            line-height: 1.2;
            font-weight: 700;

            color: var(--color-muted);

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-price {
            flex-shrink: 0;

            font-size: var(--font-price);
            line-height: 1.2;
            font-weight: 900;

            color: var(--color-gold);

            white-space: nowrap;
        }

        .currency {
            font-size: 0.75em;
        }

        /* =========================================================
           DESCRIPTION
        ========================================================= */

        .item-description {
            margin-bottom: 5px;

            font-size: var(--font-description);
            line-height: 1.35;

            color: var(--color-text);

            overflow: hidden;

            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        /* =========================================================
           SINGLE PRODUCT WITHOUT DESCRIPTION
        ========================================================= */

        .menu-card.compact-card {
            padding: 7px 12px;
        }

        .compact-card .item-info {
            justify-content: center;
        }

        .compact-row {
            display: flex;
            align-items: center;

            gap: 8px;
            min-width: 0;
            width: 100%;
        }

        .compact-row .item-name {
            flex: 1 1 auto;
            min-width: 0;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .compact-row .item-weight {
            flex-shrink: 0;
        }

        .compact-row .item-price {
            flex-shrink: 0;
        }

        /* =========================================================
           BEER
        ========================================================= */

        .beer-card .item-heading {
            margin-bottom: 5px;
        }

        .beer-percent {
            flex-shrink: 0;

            font-size: var(--font-meta);
            line-height: 1.2;
            font-weight: 900;

            color: var(--color-gold);

            white-space: nowrap;
        }

        /* =========================================================
           MILKSHAKE
        ========================================================= */

        .milkshake-card .item-heading {
            margin-bottom: 7px;
        }

        .milkshake-card .item-variants {
            gap: 4px;
        }

        .milkshake-flavors {
            display: flex;
            flex-direction: column;

            gap: 4px;

            margin-top: 10px;
            padding-top: 8px;

            border-top: 1px solid rgba(218, 183, 111, 0.25);
        }

        .milkshake-label {
            margin-bottom: 5px;
            font-size: clamp(16px, 0.9vw, 20px);
            font-weight: 900;
            color: var(--color-gold);
            text-transform: uppercase;
        }

        .milkshake-flavor {
            font-size: clamp(17px, 1.05vw, 23px);
            line-height: 1.35;
            font-weight: 600;
            color: var(--color-text);
        }

        /* =========================================================
           COCKTAILS
        ========================================================= */

        .cocktails-section .section-header {
            margin-bottom: 10px;
            padding-bottom: 9px;
        }

        .cocktails-section .section-items {
            display: grid;

            grid-template-columns: repeat(2, minmax(0, 1fr));

            grid-auto-rows: min-content;
            align-content: start;

            gap: 6px;
        }

        .cocktails-section .menu-card {
            padding: 6px 9px;
        }

        .cocktails-section .milkshake-card {
            grid-column: 1 / -1;
        }

        /* =========================================================
           EMPTY MENU
        ========================================================= */

        .menu-empty {
            width: 100%;
            height: 100%;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 30px;
            font-weight: 700;

            color: var(--color-gold);
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1200px) {

            .tv-content {
                padding: 25px;
            }

            .menu-columns {
                grid-template-columns:
                    minmax(0, 0.85fr)
                    minmax(0, 1fr)
                    minmax(0, 0.7fr)
                    minmax(0, 0.65fr);

                gap: 10px;
            }

            .left-column,
            .middle-column,
            .coffee-column,
            .beer-column {
                gap: 10px;
            }

            .menu-section {
                padding: 10px;
            }

            .menu-card {
                padding: 6px 8px;
            }

            .cocktails-section .section-items {
                gap: 4px;
            }

            .cocktails-section .menu-card {
                padding: 5px 6px;
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

            .left-column,
            .middle-column,
            .coffee-column,
            .beer-column {
                display: flex;
                flex-direction: column;
                overflow: visible;
            }

            .cocktails-section .section-items {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .menu-card {
                min-height: 0;
            }

            .milkshake-card {
                grid-column: 1 / -1;
            }
        }
    </style>
</head>

<body>

    @php
        /*
        |--------------------------------------------------------------------------
        | ГРУППИРОВКА ТОВАРОВ ПО РАЗДЕЛУ И НАЗВАНИЮ
        |--------------------------------------------------------------------------
        */
        $groupedItems = $menuItems
            ->groupBy(function ($item) {
                return (int) $item->menu_section_id . '|' . mb_strtolower(trim($item->name));
            })
            ->map(function ($items) {
                $first = $items->first();

                $descriptionItem = $items->first(function ($item) {
                    return !empty($item->description);
                });

                return (object) [
                    'name' => trim($first->name),
                    'menu_section_id' => (int) $first->menu_section_id,
                    'description' => $descriptionItem ? $descriptionItem->description : null,
                    'variants' => $items->sortBy(function ($item) {
                        return (float) $item->weight;
                    })->values(),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | КАТЕГОРИИ TV3
        | НАЗВАНИЯ РАЗДЕЛОВ ПОЛУЧАЕМ ИЗ БАЗЫ ДАННЫХ
        |--------------------------------------------------------------------------
        */
        $menuSections = [
            'homemade' => [
                'id' => 5,
                'items' => collect(),
            ],
            'cocktails' => [
                'id' => 6,
                'items' => collect(),
                'milkshake_flavors' => collect(),
            ],
            'soft' => [
                'id' => 7,
                'items' => collect(),
            ],
            'coffee' => [
                'id' => 11,
                'items' => collect(),
            ],
            'desserts' => [
                'id' => 8,
                'items' => collect(),
            ],
            'beer' => [
                'id' => 12,
                'items' => collect(),
            ],
            'confectionery' => [
                'id' => 13,
                'items' => collect(),
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | НАПОЛНЕНИЕ КАТЕГОРИЙ И ОСОБАЯ ОБРАБОТКА МОЛОЧНОГО КОКТЕЙЛЯ
        |--------------------------------------------------------------------------
        */
        foreach ($menuSections as $sectionKey => $section) {
            $sectionItems = $groupedItems
                ->filter(function ($item) use ($section) {
                    return (int) $item->menu_section_id === (int) $section['id'];
                })
                ->values();

            if ($sectionKey === 'cocktails') {
                $milkshakeFlavors = $sectionItems
                    ->filter(function ($item) {
                        $name = mb_strtolower(trim($item->name));
                        return $name !== 'молочный коктейль' && $name !== 'мохито';
                    })
                    ->map(function ($item) {
                        $flavor = preg_replace(
                            '/^молочный\s+коктейль[\s\-–—:]*/iu',
                            '',
                            trim($item->name)
                        );

                        return trim($flavor);
                    })
                    ->filter(function ($flavor) {
                        return $flavor !== '';
                    })
                    ->unique()
                    ->values();

                $sectionItems = $sectionItems
                    ->reject(function ($item) {
                        $name = mb_strtolower(trim($item->name));
                        return $name !== 'молочный коктейль' && $name !== 'мохито';
                    })
                    ->values();

                $section['milkshake_flavors'] = $milkshakeFlavors;
            }

            $section['items'] = $sectionItems;
            $menuSections[$sectionKey] = $section;
        }

        /*
        |--------------------------------------------------------------------------
        | ЧЕТЫРЕ КОЛОНКИ
        | КОНДИТЕРСКИЕ ИЗДЕЛИЯ ВРЕМЕННО НЕ ВЫВОДИМ
        |--------------------------------------------------------------------------
        */
        $menuColumns = [
            [
                'class' => 'left-column',
                'sections' => ['homemade', 'soft'],
            ],
            [
                'class' => 'middle-column',
                'sections' => ['cocktails'],
            ],
            [
                'class' => 'coffee-column',
                'sections' => ['coffee', 'desserts'],
            ],
            [
                'class' => 'beer-column',
                'sections' => ['beer'],
            ],
        ];
    @endphp

    <div class="tv-screen">
        <div class="tv-content">
            <main class="menu-columns">

                @foreach ($menuColumns as $column)
                    <div class="menu-column {{ $column['class'] }}">

                        @foreach ($column['sections'] as $sectionKey)
                            @php
                                $section = $menuSections[$sectionKey] ?? null;
                            @endphp

                            @if ($section)
                                <section class="menu-section {{ $sectionKey }}-section {{ $sectionKey === 'cocktails' ? 'cocktails-section' : '' }}">

                                    <header class="section-header">
                                        <h2 class="section-title">
                                            {{ $sectionNames[$section['id']] ?? '' }}
                                        </h2>
                                    </header>

                                    <div class="section-items">

                                        @forelse ($section['items'] as $item)

                                            @php
                                                $hasDescription = !empty($item->description);

                                                $isMilkshake = $sectionKey === 'cocktails'
                                                    && mb_strtolower(trim($item->name)) === 'молочный коктейль';

                                                $isBeer = $sectionKey === 'beer'
                                                    && $hasDescription
                                                    && preg_match('/\d+(?:[,.]\d+)?\s*%/u', $item->description);

                                                $compactCard = !$hasDescription
                                                    && !$isMilkshake
                                                    && $item->variants->count() === 1;
                                            @endphp

                                            @if ($isMilkshake)

                                                <article class="menu-card milkshake-card">
                                                    <div class="item-info">

                                                        <div class="item-heading">
                                                            <div class="item-name">
                                                                Молочный коктейль 🥤
                                                            </div>
                                                        </div>

                                                        <div class="item-variants">
                                                            @foreach ($item->variants as $variant)
                                                                <div class="variant-row">

                                                                    <span class="item-weight">
                                                                        @if ($variant->weight !== null)
                                                                            {{ $variant->weight }}
                                                                        @endif
                                                                        {{ $variant->unit }}
                                                                    </span>

                                                                    <span class="item-price">
                                                                        {{ number_format((float) $variant->price, 0, ',', ' ') }}
                                                                        <span class="currency">₽</span>
                                                                    </span>

                                                                </div>
                                                            @endforeach
                                                        </div>

                                                        @if ($section['milkshake_flavors']->isNotEmpty())
                                                            <div class="milkshake-flavors">

                                                                <div class="milkshake-label">
                                                                    Вкусы:
                                                                </div>

                                                                @foreach ($section['milkshake_flavors'] as $flavor)
                                                                    <div class="milkshake-flavor">
                                                                        {{ $flavor }}
                                                                    </div>
                                                                @endforeach

                                                            </div>
                                                        @endif

                                                    </div>
                                                </article>

                                            @elseif ($compactCard)

                                                <article class="menu-card compact-card">
                                                    <div class="item-info">

                                                        <div class="compact-row">

                                                            <span class="item-name">
                                                                {{ $item->name }}
                                                            </span>

                                                            <span class="item-weight">
                                                                @if ($item->variants->first()->weight !== null)
                                                                    {{ $item->variants->first()->weight }}
                                                                @endif
                                                                {{ $item->variants->first()->unit }}
                                                            </span>

                                                            <span class="item-price">
                                                                {{ number_format((float) $item->variants->first()->price, 0, ',', ' ') }}
                                                                <span class="currency">₽</span>
                                                            </span>

                                                        </div>

                                                    </div>
                                                </article>

                                            @else

                                                <article class="menu-card {{ $isBeer ? 'beer-card' : '' }}">
                                                    <div class="item-info">

                                                        <div class="item-heading">

                                                            <div class="item-name">
                                                                {{ $item->name }}
                                                            </div>

                                                            @if ($isBeer)
                                                                <span class="beer-percent">
                                                                    {{ $item->description }}
                                                                </span>
                                                            @endif

                                                        </div>

                                                        @if ($hasDescription && !$isBeer)
                                                            <div class="item-description">
                                                                {{ $item->description }}
                                                            </div>
                                                        @endif

                                                        <div class="item-variants">

                                                            @foreach ($item->variants as $variant)
                                                                <div class="variant-row">

                                                                    <span class="item-weight">
                                                                        @if ($variant->weight !== null)
                                                                            {{ $variant->weight }}
                                                                        @endif
                                                                        {{ $variant->unit }}
                                                                    </span>

                                                                    <span class="item-price">
                                                                        {{ number_format((float) $variant->price, 0, ',', ' ') }}
                                                                        <span class="currency">₽</span>
                                                                    </span>

                                                                </div>
                                                            @endforeach

                                                        </div>

                                                    </div>
                                                </article>

                                            @endif

                                        @empty

                                            <div class="menu-empty">
                                                Скоро в меню
                                            </div>

                                        @endforelse

                                    </div>
                                </section>
                            @endif

                        @endforeach
                    </div>
                @endforeach

            </main>
        </div>
    </div>

</body>
</html>
