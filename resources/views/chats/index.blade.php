@extends('template')

@section('title_page', __('chats.your_chats'))

@push('styles')
    @vite('resources/css/chat_index.css')
@endpush

@section('main')


<style>

/* =========================================================
   КОНТЕЙНЕР ЧАТОВ
   ========================================================= */

.chats-page-container {
    width: min(100%, 1100px);

    margin: 30px auto 40px;

    padding: 28px;

    box-sizing: border-box;

    background:
        radial-gradient(
            circle at top right,
            rgba(255, 215, 0, 0.08),
            transparent 35%
        ),
        radial-gradient(
            circle at bottom left,
            rgba(0, 220, 255, 0.06),
            transparent 35%
        ),
        linear-gradient(
            145deg,
            #0b0c18 0%,
            #111323 50%,
            #0b0c18 100%
        );

    border: 1px solid rgba(255, 215, 0, 0.75);

    border-radius: 22px;

    color: #fff;

    box-shadow:
        0 10px 35px rgba(0, 0, 0, 0.65),
        0 0 25px rgba(255, 215, 0, 0.08);

    overflow: hidden;
}


/* =========================================================
   ЗАГОЛОВОК
   ========================================================= */

.chats-page-title {
    margin: 0 0 25px;

    text-align: center;

    color: gold;

    font-size: 2rem;

    font-weight: 700;

    letter-spacing: 0.5px;

    text-shadow:
        0 0 8px rgba(255, 215, 0, 0.35),
        0 0 18px rgba(255, 215, 0, 0.12);
}


.chats-section-title {
    margin: 25px 0 15px;

    color: gold;

    text-align: center;

    font-size: 1.25rem;

    font-weight: 700;
}


/* =========================================================
   ТАБЛИЦА
   ========================================================= */

.chat-table {
    width: 100%;

    border-collapse: separate;

    border-spacing: 0;

    overflow: hidden;

    background:
        linear-gradient(
            145deg,
            #111218,
            #181a22
        );

    border: 1px solid rgba(255, 215, 0, 0.40);

    border-radius: 16px;

    box-shadow:
        0 7px 20px rgba(0, 0, 0, 0.45),
        inset 0 0 20px rgba(0, 0, 0, 0.2);
}


/* =========================================================
   ЗАГОЛОВКИ ТАБЛИЦЫ
   ========================================================= */

.chat-table thead th {
    padding: 14px 16px;

    color: gold;

    font-size: 0.95rem;

    font-weight: 700;

    text-align: left;

    background:
        linear-gradient(
            90deg,
            rgba(255, 215, 0, 0.08),
            rgba(0, 229, 255, 0.04)
        );

    border-bottom: 1px solid rgba(255, 215, 0, 0.25);
}


/* =========================================================
   ЯЧЕЙКИ
   ========================================================= */

.chat-table tbody td {
    padding: 14px 16px;

    color: #f5f5f5;

    font-size: 1rem;

    border-bottom: 1px solid rgba(255, 255, 255, 0.07);

    vertical-align: middle;
}


.chat-table tbody tr:last-child td {
    border-bottom: none;
}


.chat-table tbody tr {
    transition:
        background 0.2s ease;
}


.chat-table tbody tr:hover {
    background:
        linear-gradient(
            90deg,
            rgba(0, 229, 255, 0.07),
            rgba(255, 215, 0, 0.05)
        );
}


/* =========================================================
   НАЗВАНИЕ ЧАТА + СЧЁТЧИК
   ========================================================= */

.chat-name-cell {
    width: 55%;
}


.chat-name-line {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.chat-list-avatar {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    object-fit: cover;
    border: 1px solid rgba(0, 229, 255, .55);
    border-radius: 50%;
    box-shadow: 0 0 12px rgba(0, 229, 255, .12);
}
.chat-list-avatar-group,
.chat-list-avatar-saved {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffd700;
    background: rgba(255, 215, 0, .08);
    font-size: 1rem;
}
.chat-name-wrapper {
    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 0;
}


.chat-name-link {
    display: inline-block;

    min-width: 0;

    color: #00e5ff;

    font-size: 1.05rem;

    font-weight: 700;

    text-decoration: none;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

    transition:
        color 0.2s ease,
        text-shadow 0.2s ease;
}


.chat-name-link:hover {
    color: gold;

    text-shadow:
        0 0 10px rgba(255, 215, 0, 0.35);
}


/* =========================================================
   СЧЁТЧИК СООБЩЕНИЙ
   ========================================================= */

.chat-message-count {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    min-width: 30px;

    height: 27px;

    padding: 0 9px;

    box-sizing: border-box;

    color: #0b0c18;

    background:
        linear-gradient(
            135deg,
            #fff176,
            gold
        );

    border: 1px solid #ffe45c;

    border-radius: 15px;

    font-size: 0.82rem;

    font-weight: 800;

    line-height: 1;

    box-shadow:
        0 0 10px rgba(255, 215, 0, 0.25);
}


/* =========================================================
   УЧАСТНИКИ
   ========================================================= */

.chat-participants {
    color: #c8c8c8;

    line-height: 1.5;
}


/* =========================================================
   BADGE
   ========================================================= */

.badge {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 28px;

    height: 26px;

    padding: 0 8px;

    box-sizing: border-box;

    color: #0b0c18;

    background:
        linear-gradient(
            135deg,
            #fff176,
            gold
        );

    border-radius: 14px;

    font-size: 0.82rem;

    font-weight: 800;
}


/* =========================================================
   КНОПКА СОЗДАНИЯ
   ========================================================= */

.chat-create-wrapper {
    display: flex;

    justify-content: center;

    margin-top: 25px;
}


.des-btn {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 48px;

    padding: 10px 24px;

    box-sizing: border-box;

    color: #0b0c18;

    background:
        linear-gradient(
            135deg,
            #ffd700,
            #ffb300,
            #ff9800
        );

    border: 1px solid #ffe45c;

    border-radius: 25px;

    font-size: 1rem;

    font-weight: 800;

    text-decoration: none;

    box-shadow:
        0 5px 18px rgba(255, 215, 0, 0.20);

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        filter 0.25s ease;
}


.des-btn:hover {
    color: #000;

    filter: brightness(1.1);

    transform: translateY(-2px);

    box-shadow:
        0 8px 25px rgba(255, 215, 0, 0.45),
        0 0 18px rgba(255, 215, 0, 0.25);
}


/* =========================================================
   ПАГИНАЦИЯ
   ========================================================= */

.chats-pagination {
    display: flex;

    justify-content: center;

    margin-top: 18px;
}


/* =========================================================
   МОБИЛЬНЫЕ
   ========================================================= */

@media (max-width: 700px) {

    .chats-page-container {
        width: auto;

        margin: 15px 10px 30px;

        padding: 18px;

        border-radius: 17px;
    }


    .chats-page-title {
        font-size: 1.55rem;

        margin-bottom: 20px;
    }


    .chats-section-title {
        font-size: 1.1rem;

        margin-top: 22px;
    }


    .chat-table {
        display: table;

        width: 100%;

        table-layout: fixed;
    }


    .chat-table thead th,
    .chat-table tbody td {
        padding: 11px 9px;

        font-size: 0.9rem;
    }


    .chat-name-cell {
        width: 55%;
    }


    .chat-name-wrapper {
        gap: 6px;
    }


    .chat-name-link {
        font-size: 0.95rem;
    }


    .chat-message-count {
        min-width: 25px;

        height: 23px;

        padding: 0 6px;

        font-size: 0.72rem;
    }


    .chat-participants {
        font-size: 0.82rem;

        word-break: break-word;
    }
}


/* =========================================================
   ОЧЕНЬ МАЛЕНЬКИЕ ЭКРАНЫ
   ========================================================= */

@media (max-width: 420px) {

    .chats-page-container {
        margin-left: 6px;

        margin-right: 6px;

        padding: 13px;
    }


    .chat-table thead th,
    .chat-table tbody td {
        padding: 9px 6px;

        font-size: 0.82rem;
    }


    .chat-name-link {
        font-size: 0.88rem;
    }


    .chat-message-count {
        min-width: 23px;

        height: 21px;

        padding: 0 5px;

        font-size: 0.68rem;
    }


    .chat-participants {
        font-size: 0.75rem;
    }
}


/* =========================================================
   ПУСТАЯ ЯЧЕЙКА СЧЁТЧИКА
   ========================================================= */

/* .chat-messages-placeholder {
    min-width: 70px;
} */


/* =========================================================
   УВЕЛИЧИВАЕМ ВЕРТИКАЛЬНЫЙ ИНТЕРВАЛ МЕЖДУ ЧАТАМИ
   ========================================================= */

.chat-table tbody tr td {
    padding-top: 18px;
    padding-bottom: 18px;
    border: #ff980 solid 5px;
}


.chat-type-label {
    display: inline-flex;
    align-items: center;
    margin-left: 7px;
    padding: 2px 8px;
    color: #00e5ff;
    font-size: .72rem;
    border: 1px solid rgba(0, 229, 255, .3);
    border-radius: 12px;
    white-space: nowrap;
}


.chat-stories-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    margin: 14px 0 20px;
    padding: 8px 10px;
    border: 1px solid rgba(255, 215, 0, .24);
    border-left: 3px solid #00e5ff;
    border-radius: 13px;
    background: linear-gradient(110deg, rgba(0,229,255,.045), rgba(255,215,0,.035));
}
.chat-story-add, .chat-story-card {
    display: flex;
    flex: 0 0 62px;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 0;
    border: 0;
    color: #dbe4f0;
    background: transparent;
    font-size: .66rem;
    cursor: pointer;
}
.chat-story-add-avatar, .chat-story-avatar {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    overflow: hidden;
    border: 2px solid #00e5ff;
    border-radius: 50%;
    color: #ffd700;
    background: #111827;
    font-size: 1.25rem;
    object-fit: cover;
}
.chat-story-card.unviewed .chat-story-avatar {
    border: 3px solid transparent;
    background:
        linear-gradient(#111827,#111827) padding-box,
        linear-gradient(135deg,#00e5ff,#ffd700) border-box;
}
.chat-story-card.viewed .chat-story-avatar { border-color: #64748b; opacity: .75; }
.chat-story-name { max-width: 62px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.chat-story-viewer {
    position: fixed;
    inset: 0;
    z-index: 100100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 56px 12px 18px;
    background: rgba(0,0,0,.96);
}
.chat-story-viewer[hidden] { display: none; }
.chat-story-viewer-content {
    max-width: min(94vw, 620px);
    max-height: calc(100vh - 90px);
    object-fit: contain;
    border: 1px solid rgba(0,229,255,.35);
    border-radius: 12px;
}
.chat-story-viewer-close {
    position: absolute;
    top: 12px;
    right: 14px;
    width: 42px;
    height: 42px;
    border: 1px solid rgba(255,215,0,.5);
    border-radius: 50%;
    color: #ffd700;
    background: #111827;
    font-size: 28px;
}
.chat-story-viewer-info {
    position: absolute;
    top: 18px;
    left: 18px;
    color: #fff;
    font-weight: 700;
}
.chat-story-viewer-actions {
    position: absolute;
    right: 16px;
    bottom: 14px;
    display: flex;
    gap: 8px;
}
.chat-story-viewer-actions button {
    padding: 8px 12px;
    border: 1px solid rgba(0,229,255,.45);
    border-radius: 8px;
    color: #00e5ff;
    background: rgba(12,18,30,.9);
}
@media (max-width: 600px) {
    .chat-stories-bar { margin: 12px -8px 22px; border-radius: 0; }
    .chat-story-viewer-content { max-width: 100%; max-height: calc(100vh - 80px); }
}



/* =========================================================
   STORIES — polished horizontal story rail
   ========================================================= */
.chat-stories-bar {
    display: flex;
    align-items: stretch;
    gap: 12px;
    min-width: 0;
    margin: 18px 0 24px;
    padding: 13px 14px;
    overflow: hidden;
    border: 1px solid rgba(0, 229, 255, .18);
    border-radius: 18px;
    background:
        radial-gradient(circle at 0 0, rgba(0, 229, 255, .09), transparent 42%),
        linear-gradient(135deg, rgba(12, 20, 34, .96), rgba(17, 20, 34, .92));
    box-shadow: inset 0 1px 0 rgba(255,255,255,.035), 0 8px 24px rgba(0,0,0,.18);
}
.chat-story-add,
.chat-story-card {
    display: flex;
    flex: 0 0 76px;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 7px;
    min-width: 0;
    padding: 2px 3px 4px;
    color: #dce6f2;
    background: transparent;
    border: 0;
    border-radius: 12px;
    font: inherit;
    font-size: .72rem;
    line-height: 1.2;
    text-align: center;
    cursor: pointer;
    transition: background .18s ease, transform .18s ease;
}
.chat-story-add:hover,
.chat-story-card:hover {
    background: rgba(0, 229, 255, .07);
    transform: translateY(-2px);
}
.chat-story-add:focus-visible,
.chat-story-card:focus-visible {
    outline: 2px solid #00e5ff;
    outline-offset: 2px;
}
.chat-story-add-avatar,
.chat-story-avatar {
    display: grid;
    place-items: center;
    flex: 0 0 58px;
    width: 58px;
    height: 58px;
    padding: 3px;
    overflow: hidden;
    color: #ffd700;
    background: #101827;
    border: 2px solid rgba(0, 229, 255, .72);
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 0 0 4px rgba(0, 229, 255, .055), 0 5px 14px rgba(0,0,0,.28);
}
.chat-story-add-avatar {
    color: #071018;
    background: linear-gradient(135deg, #00e5ff, #00bcd4);
    border: 2px solid rgba(0, 229, 255, .85);
    font-size: 1.65rem;
    font-weight: 400;
}
.chat-story-card.unviewed .chat-story-avatar {
    padding: 3px;
    border: 3px solid transparent;
    background:
        linear-gradient(#101827, #101827) padding-box,
        linear-gradient(135deg, #00e5ff 0%, #ffd700 52%, #00e5ff 100%) border-box;
    box-shadow: 0 0 0 3px rgba(0, 229, 255, .08), 0 0 15px rgba(0, 229, 255, .13);
}
.chat-story-card.viewed .chat-story-avatar {
    border: 2px solid #64748b;
    opacity: .68;
    filter: saturate(.72);
}
.chat-story-name {
    display: block;
    width: 100%;
    max-width: 72px;
    overflow: hidden;
    color: #cbd5e1;
    font-size: .69rem;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.chat-stories-list {
    display: flex;
    flex: 1 1 auto;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 2px 2px 6px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,229,255,.35) transparent;
    overscroll-behavior-x: contain;
}
.chat-stories-list::-webkit-scrollbar { height: 4px; }
.chat-stories-list::-webkit-scrollbar-thumb { background: rgba(0,229,255,.35); border-radius: 5px; }
.chat-stories-list .chat-story-name { max-width: 72px; }
.chat-stories-list > .chat-story-name {
    align-self: center;
    color: #94a3b8;
    font-size: .82rem;
}
.chat-story-viewer {
    position: fixed;
    inset: 0;
    z-index: 100100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: max(66px, env(safe-area-inset-top)) 14px max(76px, env(safe-area-inset-bottom));
    background: rgba(3, 7, 15, .97);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.chat-story-viewer[hidden] { display: none !important; }
.chat-story-viewer-content {
    display: block;
    max-width: min(92vw, 680px);
    max-height: min(78dvh, 900px);
    object-fit: contain;
    border: 1px solid rgba(0,229,255,.32);
    border-radius: 14px;
    box-shadow: 0 16px 55px rgba(0,0,0,.55);
}
.chat-story-viewer-close {
    position: absolute;
    top: max(12px, env(safe-area-inset-top));
    right: max(14px, env(safe-area-inset-right));
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    color: #ffd700;
    background: rgba(15, 23, 42, .94);
    border: 1px solid rgba(255,215,0,.42);
    border-radius: 50%;
    font-size: 27px;
    cursor: pointer;
}
.chat-story-viewer-info {
    position: absolute;
    top: max(20px, env(safe-area-inset-top));
    left: max(18px, env(safe-area-inset-left));
    max-width: calc(100% - 90px);
    overflow: hidden;
    color: #f8fafc;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.chat-story-viewer-actions {
    position: absolute;
    right: max(16px, env(safe-area-inset-right));
    bottom: max(14px, env(safe-area-inset-bottom));
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.chat-story-viewer-actions button {
    min-height: 40px;
    padding: 8px 13px;
    color: #00e5ff;
    background: rgba(12,18,30,.94);
    border: 1px solid rgba(0,229,255,.4);
    border-radius: 10px;
    cursor: pointer;
}
.chat-story-viewer-actions button:hover {
    color: #071018;
    background: #00e5ff;
}
@media (max-width: 600px) {
    .chat-stories-bar {
        gap: 9px;
        margin: 14px -5px 20px;
        padding: 10px 9px;
        border-radius: 14px;
    }
    .chat-story-add,
    .chat-story-card { flex-basis: 68px; gap: 6px; }
    .chat-story-add-avatar,
    .chat-story-avatar {
        flex-basis: 52px;
        width: 52px;
        height: 52px;
    }
    .chat-story-name,
    .chat-stories-list .chat-story-name { max-width: 66px; font-size: .66rem; }
    .chat-story-viewer-content {
        max-width: 100%;
        max-height: calc(100dvh - 150px);
    }
}


/* Instagram-like story progress, navigation and reactions */
.chat-story-progress {
    position: absolute;
    z-index: 3;
    top: max(12px, env(safe-area-inset-top));
    left: max(14px, env(safe-area-inset-left));
    right: max(68px, env(safe-area-inset-right));
    display: flex;
    gap: 4px;
}
.chat-story-progress-track {
    flex: 1 1 0;
    height: 3px;
    overflow: hidden;
    background: rgba(255,255,255,.28);
    border-radius: 5px;
}
.chat-story-progress-track i {
    display: block;
    width: 0;
    height: 100%;
    background: #fff;
    border-radius: inherit;
    transition: width .05s linear;
}
.chat-story-navigation {
    position: absolute;
    z-index: 2;
    inset: 64px 0 64px;
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    pointer-events: none;
}
.chat-story-navigation button {
    width: min(20vw, 110px);
    color: transparent;
    background: transparent;
    border: 0;
    cursor: pointer;
    pointer-events: auto;
}
.chat-story-navigation button:focus-visible {
    color: #fff;
    background: rgba(0,0,0,.2);
    outline: 1px solid rgba(255,255,255,.4);
}
.chat-story-navigation button:disabled { visibility: hidden; }
.chat-story-reactions {
    position: absolute;
    z-index: 4;
    left: 50%;
    bottom: max(16px, env(safe-area-inset-bottom));
    display: flex;
    gap: 7px;
    padding: 7px;
    transform: translateX(-50%);
    background: rgba(8,14,25,.78);
    border: 1px solid rgba(0,229,255,.22);
    border-radius: 28px;
    backdrop-filter: blur(12px);
}
.chat-story-reactions button {
    display: grid;
    place-items: center;
    width: 39px;
    height: 39px;
    padding: 0;
    color: #fff;
    background: rgba(255,255,255,.08);
    border: 1px solid transparent;
    border-radius: 50%;
    font-size: 21px;
    cursor: pointer;
    transition: transform .16s ease, background .16s ease;
}
.chat-story-reactions button:hover { transform: translateY(-3px) scale(1.08); }
.chat-story-reactions button.active {
    background: rgba(0,229,255,.2);
    border-color: #00e5ff;
    box-shadow: 0 0 12px rgba(0,229,255,.28);
}
.chat-stories-list { flex-wrap: nowrap; }
.chat-stories-list > .chat-story-card { flex: 0 0 76px; }
@media (max-width: 600px) {
    .chat-story-reactions { gap: 4px; padding: 5px; }
    .chat-story-reactions button { width: 34px; height: 34px; font-size: 19px; }
}


.chat-story-private-reply {
    position: absolute;
    z-index: 5;
    left: 50%;
    bottom: max(68px, calc(env(safe-area-inset-bottom) + 62px));
    transform: translateX(-50%);
    min-height: 38px;
    padding: 8px 14px;
    color: #071018;
    background: linear-gradient(135deg, #00e5ff, #5eead4);
    border: 0;
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 6px 22px rgba(0,0,0,.4);
    cursor: pointer;
}
.chat-story-private-reply:hover { filter: brightness(1.08); }


/* DAODES MESSENGER heading and compact navigation */
.chats-page-heading {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 54px;
    margin: 0 0 22px;
}
.chats-page-heading .chats-page-title {
    margin: 0;
    flex: 1 1 auto;
    min-width: 0;
    font-size: clamp(1.15rem, 3vw, 2rem);
    white-space: nowrap;
}
.messenger-menu {
    position: absolute;
    right: 0;
    top: 50%;
    z-index: 1000;
    transform: translateY(-50%);
}
.messenger-menu-toggle {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    padding: 0;
    color: #00e5ff;
    background: transparent;
    border: 1px solid rgba(0,229,255,.42);
    border-radius: 11px;
    font-size: 1.25rem;
    cursor: pointer;
}
.messenger-menu-toggle:hover,
.messenger-menu-toggle[aria-expanded="true"] {
    color: #071018;
    background: #00e5ff;
}
.messenger-menu-panel {
    position: absolute;
    top: calc(100% + 9px);
    right: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
    width: min(260px, calc(100vw - 48px));
    padding: 8px;
    background: #111827;
    border: 1px solid rgba(0,229,255,.35);
    border-radius: 13px;
    box-shadow: 0 12px 32px rgba(0,0,0,.5);
}
.messenger-menu-panel[hidden] { display: none !important; }
.messenger-menu-panel a {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 42px;
    padding: 8px 11px;
    color: #e5edf7;
    text-decoration: none;
    border-radius: 8px;
}
.messenger-menu-panel a i {
    width: 20px;
    color: #00e5ff;
    text-align: center;
}
.messenger-menu-panel a:hover {
    color: #ffd700;
    background: rgba(0,229,255,.08);
}
.chat-stories-bar {
    flex-wrap: nowrap;
    min-width: 0;
}
.chat-stories-list {
    flex-flow: row nowrap;
    align-items: flex-start;
    white-space: nowrap;
}
.chat-story-card {
    flex: 0 0 76px;
}
@media (max-width: 600px) {
    .chats-page-heading { min-height: 46px; padding-right: 46px; }
    .chats-page-heading .chats-page-title { font-size: clamp(.95rem, 4.2vw, 1.35rem); }
    .messenger-menu-toggle { width: 38px; height: 38px; }
}

</style>


<div class="container chats-page">

        {{-- =====================================================
             ЗАГОЛОВОК
             ===================================================== --}}

        <div class="chats-page-heading">
            <h1 class="big text-center chats-page-title">
                DAODES MESSENGER
            </h1>

            <div class="messenger-menu">
                <button
                    type="button"
                    class="messenger-menu-toggle"
                    id="messengerMenuToggle"
                    aria-label="Открыть меню мессенджера"
                    aria-expanded="false"
                    aria-controls="messengerMenu"
                >
                    <i class="fa fa-bars" aria-hidden="true"></i>
                </button>

                <nav class="messenger-menu-panel" id="messengerMenu" aria-label="Меню мессенджера" hidden>
                    <a href="{{ route('contacts.index') }}">
                        <i class="fa fa-address-book" aria-hidden="true"></i>
                        <span>Контакты</span>
                    </a>
                    <a href="{{ route('chats.notifications') }}">
                        <i class="fa fa-bell" aria-hidden="true"></i>
                        <span>Уведомления</span>
                    </a>
                    <a href="{{ route('chats.index') }}">
                        <i class="fa fa-comments" aria-hidden="true"></i>
                        <span>Чаты</span>
                    </a>
                    <a href="{{ route('chats.create') }}">
                        <i class="fa fa-plus-circle" aria-hidden="true"></i>
                        <span>Создать чат / канал</span>
                    </a>
                </nav>
            </div>
        </div>

        <section class="chat-stories-bar" id="chatStoriesBar"
                 data-user-id="{{ auth()->id() }}"
                 data-csrf="{{ csrf_token() }}"
                 data-label-my-story="{{ __('chats.my_story') }}"
                 data-label-story-views="{{ __('chats.story_views') }}"
                 data-label-story-delete="{{ __('chats.story_delete') }}"
                 data-label-stories-empty="{{ __('chats.stories_empty') }}"
                 data-label-story-upload-error="{{ __('chats.story_upload_error') }}">
            <button type="button" class="chat-story-add" id="addChatStoryBtn">
                <span class="chat-story-add-avatar">＋</span>
                <span>{{ __('chats.add_story') }}</span>
            </button>
            <div class="chat-stories-list" id="chatStoriesList" aria-live="polite"></div>
            <input type="file" id="chatStoryFileInput" accept="image/*,video/*" hidden>
        </section>


        {{-- =====================================================
             ГРУППОВЫЕ ЧАТЫ
             ===================================================== --}}

        <h2 class="text-center chats-section-title">
            {{ __('chats.group_chats') }}
        </h2>


        <table class="chat-table">

            <thead>
                <tr>
                    <th>
                        {{ __('chats.chat_name') }}
                    </th>

                    <th>
                        {{ __('chats.messages_count') }}
                    </th>

                    <th>
                        {{ __('chats.participants') }}
                    </th>
                </tr>
            </thead>


            <tbody>

                @foreach ($groupChats as $chat)

                    <tr class="chat-row">

                        {{-- =================================================
                             НАЗВАНИЕ + СЧЁТЧИК
                             ================================================= --}}

                        <td class="chat-name-cell">

                            <div class="chat-name-line">

                                @if ($chat->type === 'channel' || $chat->type === 'group')
                                    <span class="chat-list-avatar chat-list-avatar-group" aria-hidden="true">
                                        <i class="fa fa-users"></i>
                                    </span>
                                @endif

                                <a
                                    href="{{ route('chats.show', $chat->id) }}"
                                    class="chat-name-link"
                                >
                                    {{ $chat->getChatNameForUser(auth()->id()) }}
                                </a>

                                @if ($chat->type === 'channel')
                                    <span class="chat-type-label">{{ __('chats.channel') }}</span>
                                @endif


                                {{-- Счётчик показываем только если > 0 --}}

                                @if (($uniqueChats[$chat->id] ?? 0) > 0)

                                    <span class="badge chat-message-badge">
                                        {{ $uniqueChats[$chat->id] }}
                                    </span>

                                @endif

                            </div>

                        </td>


                        {{-- =================================================
                             СЧЁТЧИК ДЛЯ ДЕСКТОПНОЙ ВЕРСИИ
                             На мобильном скрывается CSS
                             ================================================= --}}

                        {{-- <td class="chat-count-cell">

                            @if (($uniqueChats[$chat->id] ?? 0) > 0)

                                <span class="badge">
                                    {{ $uniqueChats[$chat->id] }}
                                </span>

                            @endif

                        </td> --}}


                        {{-- =================================================
                             УЧАСТНИКИ
                             ================================================= --}}

                        <td class="chat-participants-cell">

                            <div class="chat-participants">

                                @foreach ($chat->participants as $participant)

                                    <span class="chat-participant">
                                        {{ $participant->name }}
                                    </span>

                                    @if (!$loop->last)
                                        <span class="chat-participant-separator">,</span>
                                    @endif

                                @endforeach

                            </div>

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>


        {{-- =====================================================
             ПАГИНАЦИЯ ГРУППОВЫХ ЧАТОВ
             ===================================================== --}}

        <div class="pagination chat-pagination">

            {{ $groupChats->links() }}

        </div>



        {{-- =====================================================
             ЛИЧНЫЕ СООБЩЕНИЯ
             ===================================================== --}}

        <table class="chat-table private-chat-table">

            <thead>

                <tr>

                    <th>

                        <h4 class="text-center chats-private-title">
                            {{ __('chats.private_messages') }}
                        </h4>

                    </th>

                    <th>
                        {{ __('chats.messages_count') }}
                    </th>

                </tr>

            </thead>


            <tbody>

                @foreach ($privateChats as $chat)

                    <tr class="chat-row private-chat-row">

                        {{-- =================================================
                             НАЗВАНИЕ + СЧЁТЧИК
                             ================================================= --}}

                        <td class="chat-name-cell">

                            <div class="chat-name-line">

                                @if ($chat->type === 'saved')
                                    <span class="chat-list-avatar chat-list-avatar-saved" aria-hidden="true">
                                        <i class="fa fa-bookmark"></i>
                                    </span>
                                @else
                                    @php
                                        $chatOtherUser = $chat->users->first(fn($participant) => $participant->id !== auth()->id());
                                    @endphp
                                    <img
                                        class="chat-list-avatar"
                                        src="{{ $chatOtherUser?->profile?->avatar_url ?: '/img/main/default-avatar.png' }}"
                                        alt=""
                                        loading="lazy"
                                    >
                                @endif

                                <a
                                    href="{{ route('chats.show', $chat->id) }}"
                                    class="chat-name-link"
                                >
                                    {{ $chat->getChatNameForUser(auth()->id()) }}
                                </a>


                                {{-- Счётчик показываем только если > 0 --}}

                                @if (($uniqueChats[$chat->id] ?? 0) > 0)

                                    <span class="badge chat-message-badge">
                                        {{ $uniqueChats[$chat->id] }}
                                    </span>

                                @endif

                            </div>

                        </td>


                        {{-- =================================================
                             СЧЁТЧИК ДЛЯ ДЕСКТОПА
                             ================================================= --}}

                        <td class="chat-count-cell">

                            @if (($uniqueChats[$chat->id] ?? 0) > 0)

                                <span class="badge">
                                    {{ $uniqueChats[$chat->id] }}
                                </span>

                            @endif

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>


        {{-- =====================================================
             ПАГИНАЦИЯ ЛИЧНЫХ ЧАТОВ
             ===================================================== --}}

        <div class="pagination chat-pagination">

            {{ $privateChats->links() }}

        </div>



        {{-- =====================================================
             КНОПКА СОЗДАНИЯ ЧАТА
             ===================================================== --}}

        <div class="text-center chat-create-wrapper">
            <button
                type="button"
                id="openSavedMessages"
                class="des-btn"
                data-user-id="{{ auth()->id() }}"
            >
                <i class="fa fa-bookmark" aria-hidden="true"></i>
                {{ __('chats.saved_messages') }}
            </button>
        </div>

    </div>


    {{-- =========================================================
         JAVASCRIPT
         ========================================================= --}}

    @if (auth()->check())

        @push('scripts')

            @vite('resources/js/chat_index.js')

        @endpush

    @endif

@endsection