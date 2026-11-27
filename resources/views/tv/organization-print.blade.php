<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $organization->name }} — Полное меню</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            color: #17211d;
            background: #eef1ed;
            font-family: Arial, Helvetica, sans-serif;
        }
        .print-toolbar {
            max-width: 1100px;
            margin: 0 auto 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .print-button {
            border: 0;
            border-radius: 7px;
            padding: 11px 18px;
            color: #fff;
            background: #176b60;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }
        .menu-sheet {
            max-width: 1100px;
            margin: 0 auto;
            padding: 28px;
            background: #fff;
            box-shadow: 0 5px 25px rgba(0,0,0,.12);
        }
        .menu-header {
            margin-bottom: 22px;
            padding-bottom: 14px;
            text-align: center;
            border-bottom: 2px solid #176b60;
        }
        h1 {
            margin: 0;
            color: #123d35;
            font-size: 27px;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        .menu-subtitle {
            margin-top: 6px;
            color: #63716a;
            font-size: 12px;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 22px;
            align-items: start;
        }
        .menu-section {
            min-width: 0;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .section-title {
            margin: 0 0 8px;
            padding: 0 0 6px;
            color: #176b60;
            border-bottom: 1px solid #a9c7bd;
            font-size: 15px;
            text-transform: uppercase;
        }
        .menu-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            padding: 6px 0;
            border-bottom: 1px dotted #d4ddd8;
            break-inside: avoid;
        }
        .item-name {
            color: #1e2924;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
        }
        .item-description {
            margin-top: 3px;
            color: #64716b;
            font-size: 10px;
            line-height: 1.3;
        }
        .item-meta {
            min-width: 64px;
            text-align: right;
            white-space: nowrap;
        }
        .item-price {
            color: #123d35;
            font-size: 12px;
            font-weight: 800;
        }
        .item-weight {
            display: block;
            margin-top: 2px;
            color: #68766f;
            font-size: 10px;
        }
        .menu-empty {
            padding: 30px;
            color: #68766f;
            text-align: center;
        }
        @page { size: A4 portrait; margin: 10mm; }
        @media (max-width: 700px) {
            body { padding: 10px; }
            .menu-sheet { padding: 16px; }
            .menu-grid { grid-template-columns: 1fr; }
        }
        @media print {
            html, body { margin: 0; padding: 0; background: #fff; }
            .print-toolbar { display: none !important; }
            .menu-sheet {
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .menu-grid { gap: 12px 16px; }
            .section-title { color: #111; border-color: #777; }
            .item-name, .item-price { color: #111; }
            .item-description, .item-weight, .menu-subtitle { color: #444; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button class="print-button" type="button" onclick="window.print()">Печать / сохранить PDF · A4</button>
    </div>

    <main class="menu-sheet">
        <header class="menu-header">
            <h1>{{ $organization->name }}</h1>
            <div class="menu-subtitle">Полное меню</div>
        </header>

        @if ($sections->isNotEmpty())
            <div class="menu-grid">
                @foreach ($sections as $section)
                    <section class="menu-section">
                        <h2 class="section-title">{{ $section->name }}</h2>
                        @foreach ($section->items as $item)
                            <article class="menu-item">
                                <div>
                                    <div class="item-name">{{ $item->name }}</div>
                                    @if ($item->description)
                                        <div class="item-description">{{ $item->description }}</div>
                                    @endif
                                </div>
                                <div class="item-meta">
                                    <div class="item-price">{{ number_format((float) $item->price, 0, ',', ' ') }} ₽</div>
                                    @if ($item->weight !== null || $item->unit)
                                        <span class="item-weight">{{ $item->weight }} {{ $item->unit }}</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </section>
                @endforeach
            </div>
        @else
            <div class="menu-empty">В меню пока нет активных позиций.</div>
        @endif
    </main>
</body>
</html>
