<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#07130f">
    <title>{{ $organization->name }} — {{ $screenName }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { width: 100%; min-height: 100%; margin: 0; }
        body {
            min-height: 100vh;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 15% 10%, rgba(0, 229, 212, .15), transparent 35%),
                radial-gradient(circle at 85% 85%, rgba(212, 175, 55, .13), transparent 35%),
                #07130f;
            background-image: @if($backgroundUrl) url('{{ $backgroundUrl }}'), @endif
                radial-gradient(circle at 15% 10%, rgba(0, 229, 212, .15), transparent 35%),
                radial-gradient(circle at 85% 85%, rgba(212, 175, 55, .13), transparent 35%),
                linear-gradient(135deg, #07130f, #101a18 55%, #07130f);
            background-size: @if($backgroundUrl) {{ $backgroundFit === 'stretch' ? '100% 100%' : $backgroundFit }}, @endif cover, cover, cover;
            background-position: center;
            background-attachment: fixed;
        }
        .tv-page { min-height: 100vh; padding: clamp(18px, 2.4vw, 46px); }
        .tv-heading {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin: 0 auto 28px; max-width: 1900px;
            padding: 18px 26px; border: 1px solid rgba(212,175,55,.65);
            border-radius: 18px; background: rgba(4,15,12,.82);
            box-shadow: 0 12px 35px rgba(0,0,0,.35);
        }
        .tv-brand { color: #f5d58a; font-size: clamp(24px, 3vw, 48px); font-weight: 900; letter-spacing: 1px; }
        .tv-number {
            flex: 0 0 auto; padding: 12px 22px; border: 1px solid #4ecdc4;
            border-radius: 30px; color: #4ecdc4; font-weight: 900;
            font-size: clamp(15px, 1.5vw, 25px); letter-spacing: 2px;
            background: rgba(10,55,50,.75);
        }
        .tv-sections {
            max-width: 1900px; margin: 0 auto;
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: start; gap: 20px;
        }
        .tv-section {
            min-width: 0; padding: 18px; border: 1px solid rgba(78,205,196,.48);
            border-radius: 16px; background: rgba(5,18,14,.86);
            box-shadow: 0 8px 28px rgba(0,0,0,.28);
        }
        .tv-section-title {
            margin: 0 0 14px; padding: 0 0 12px;
            color: #f5d58a; border-bottom: 2px solid rgba(212,175,55,.55);
            font-size: clamp(22px, 2vw, 34px); line-height: 1.15;
            text-transform: uppercase; letter-spacing: .7px;
        }
        .tv-items { display: grid; gap: 10px; }
        .tv-item {
            display: grid; grid-template-columns: minmax(0, 1fr) auto;
            align-items: center; gap: 10px; padding: 12px 14px;
            border: 1px solid rgba(218,183,111,.35); border-radius: 11px;
            background: rgba(13,34,26,.86);
        }
        .tv-item-name { color: #f5d58a; font-size: clamp(17px, 1.35vw, 24px); font-weight: 800; line-height: 1.2; }
        .tv-item-description { margin-top: 5px; color: #e4e4dc; font-size: clamp(13px, 1vw, 17px); line-height: 1.25; }
        .tv-item-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; white-space: nowrap; }
        .tv-item-weight { color: #9caa9f; font-size: clamp(13px, 1vw, 17px); }
        .tv-item-price { color: #ffd700; font-size: clamp(20px, 1.65vw, 29px); font-weight: 900; }
        .tv-empty {
            grid-column: 1 / -1; padding: 40px 20px; text-align: center;
            color: #f5d58a; font-size: clamp(20px, 2vw, 30px);
            border: 1px dashed rgba(212,175,55,.5); border-radius: 16px;
            background: rgba(5,18,14,.75);
        }
        @media (max-width: 1100px) { .tv-sections { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 650px) {
            .tv-page { padding: 10px; }
            .tv-heading { margin-bottom: 12px; padding: 14px; border-radius: 12px; }
            .tv-brand { font-size: clamp(18px, 5vw, 27px); }
            .tv-number { padding: 9px 12px; font-size: 12px; letter-spacing: 1px; }
            .tv-sections { grid-template-columns: 1fr; gap: 12px; }
            .tv-section { padding: 13px; }
            .tv-section-title { font-size: 21px; }
            .tv-item { padding: 10px; }
            .tv-item-name { font-size: 17px; }
            .tv-item-description { font-size: 13px; }
            .tv-item-price { font-size: 21px; }
        }
    </style>
</head>
<body>
    <main class="tv-page">
        <header class="tv-heading">
            <div class="tv-brand">{{ $organization->name }}</div>
            <div class="tv-number">МЕНЮ · {{ $screenName }}</div>
        </header>

        <div class="tv-sections">
            @forelse ($sections as $section)
                <section class="tv-section">
                    <h2 class="tv-section-title">{{ $section->name }}</h2>
                    <div class="tv-items">
                        @forelse ($section->items as $item)
                            <article class="tv-item">
                                <div>
                                    <div class="tv-item-name">{{ $item->name }}</div>
                                    @if ($item->description)
                                        <div class="tv-item-description">{{ $item->description }}</div>
                                    @endif
                                </div>
                                <div class="tv-item-meta">
                                    @if ($item->weight !== null || $item->unit)
                                        <span class="tv-item-weight">{{ $item->weight }} {{ $item->unit }}</span>
                                    @endif
                                    <span class="tv-item-price">{{ number_format((float) $item->price, 0, ',', ' ') }} ₽</span>
                                </div>
                            </article>
                        @empty
                            <div class="tv-item-description">Скоро в меню</div>
                        @endforelse
                    </div>
                </section>
            @empty
                <div class="tv-empty">Для этого экрана пока не назначены разделы меню.</div>
            @endforelse
        </div>
    </main>
    @if($rotationSeconds > 0 && $screenCount > 1)
        <script>
            (() => {
                const nextScreen = {{ $screen >= $screenCount ? 1 : $screen + 1 }};
                window.setTimeout(() => {
                    const nextUrl = new URL(window.location.href);
                    nextUrl.searchParams.set('screen', String(nextScreen));
                    window.location.replace(nextUrl.toString());
                }, {{ $rotationSeconds * 1000 }});
            })();
        </script>
    @endif
</body>
</html>
