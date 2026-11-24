@extends('template')

@section('title_page', __('chats.chat'))
@section('body_class', 'chat-page')
@section('hide_footer', true)

@push('styles')
    @vite('resources/css/chat_show.css')
@endpush

@section('main')

<style>

/* =========================================================
   ОСНОВНАЯ КАРТОЧКА ЧАТА
   ========================================================= */

.chat-container {
    position: relative;

    width: min(92%, 1100px);

    margin: 30px auto 40px;

    padding: 30px;

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
   ВЕРХНЯЯ ЛИНИЯ
   ========================================================= */

.chat-container::before {
    content: "";

    position: absolute;

    top: 0;
    left: 8%;
    right: 8%;

    height: 2px;

    background:
        linear-gradient(
            90deg,
            transparent,
            gold,
            #fff176,
            gold,
            transparent
        );

    box-shadow:
        0 0 12px rgba(255, 215, 0, 0.7);
}


/* =========================================================
   НИЖНЯЯ ЛИНИЯ
   ========================================================= */

.chat-container::after {
    content: "";

    position: absolute;

    bottom: 0;
    left: 15%;
    right: 15%;

    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(0, 229, 255, 0.8),
            transparent
        );
}


/* =========================================================
   ЗАГОЛОВОК
   ========================================================= */


.chat-message-search { display: flex; align-items: center; gap: 7px; margin-top: 10px; }
.chat-message-search > button {
    min-width: 34px;
    min-height: 34px;
    border: 1px solid rgba(0,229,255,.35);
    border-radius: 8px;
    color: #00e5ff;
    background: rgba(0,229,255,.05);
}
#messageSearchPanel { display: flex; align-items: center; gap: 6px; }
#messageSearchPanel[hidden] { display: none; }
#chatMessageSearchInput {
    width: min(260px, 45vw);
    padding: 7px 9px;
    border: 1px solid rgba(0,229,255,.35);
    border-radius: 8px;
    color: #fff;
    background: #090f1b;
}
#chatMessageSearchCount { color: #ffd700; font-size: .75rem; }
#clearMessageSearch {
    border: 1px solid rgba(255,215,0,.35);
    border-radius: 7px;
    color: #ffd700;
    background: rgba(255,215,0,.05);
}
.chat-person-heading {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
}
.chat-header-saved-avatar {
    display: grid;
    place-items: center;
    color: #ffd700;
    background: rgba(255,215,0,.08);
    font-size: 1.25rem;
}
.chat-header-avatar {
    width: 54px;
    height: 54px;
    flex: 0 0 54px;
    object-fit: cover;
    border: 2px solid #00e5ff;
    border-radius: 50%;
    box-shadow: 0 0 16px rgba(0, 229, 255, .22);
}
.chat-person-heading-info { min-width: 0; }
.chat-custom-status {
    margin: 2px 0 0;
    color: #ffd700;
    font-size: .82rem;
    overflow-wrap: anywhere;
}
.chat-person-heading-info .chat-title,
.chat-person-heading-info .chat-status { margin-top: 0; margin-bottom: 3px; }
@media (max-width: 600px) {
    .chat-header-avatar { width: 44px; height: 44px; flex-basis: 44px; }
}
.chat-header {
    text-align: center;

    margin-bottom: 25px;

    padding-bottom: 20px;

    border-bottom: 1px solid rgba(255, 215, 0, 0.20);
}

.chat-mute-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}
.chat-mute-controls select,
.chat-mute-controls button {
    min-height: 38px;
    padding: 7px 12px;
    color: #e5e7eb;
    background: #151923;
    border: 1px solid rgba(0, 229, 255, 0.35);
    border-radius: 9px;
}
.chat-mute-controls button {
    color: gold;
    border-color: rgba(255, 215, 0, 0.45);
    cursor: pointer;
}
.chat-mute-status {
    flex-basis: 100%;
    color: #aeb7c5;
    font-size: 0.82rem;
}


.chat-title {
    margin: 0;

    color: gold;

    font-size: 2rem;

    font-weight: 700;

    letter-spacing: 0.5px;

    text-shadow:
        0 0 8px rgba(255, 215, 0, 0.35),
        0 0 18px rgba(255, 215, 0, 0.12);
}


.chat-status {
    margin: 10px 0 0;

    color: #9ca3af;

    font-size: 1rem;
}


/* =========================================================
   ИНДИКАТОРЫ ONLINE / OFFLINE
   ========================================================= */

.status-indicator {
    display: inline-block;

    width: 10px;

    height: 10px;

    margin-right: 7px;

    border-radius: 50%;

    vertical-align: middle;
}


.status-indicator.online {
    background: #00e676;

    box-shadow:
        0 0 8px rgba(0, 230, 118, 0.8);
}


.status-indicator.offline {
    background: #777;

    box-shadow:
        0 0 6px rgba(150, 150, 150, 0.4);
}


/* =========================================================
   ОБЛАСТЬ СООБЩЕНИЙ
   ========================================================= */

.chat-messages {
    height: 550px;

    overflow-y: auto;

    padding: 20px;

    box-sizing: border-box;

    background:
        linear-gradient(
            145deg,
            rgba(0, 0, 0, 0.35),
            rgba(255, 255, 255, 0.015)
        );

    border: 1px solid rgba(0, 229, 255, 0.20);

    border-radius: 18px;

    box-shadow:
        inset 0 0 25px rgba(0, 0, 0, 0.35);

    scroll-behavior: smooth;
}


/* =========================================================
   СООБЩЕНИЕ
   ========================================================= */

.message {
    position: relative;

    display: flex;

    flex-direction: column;

    max-width: 78%;

    margin-bottom: 16px;

    padding: 0;

    box-sizing: border-box;
}


.message.sent {
    margin-left: auto;

    align-items: flex-end;
}


.message.received {
    margin-right: auto;

    align-items: flex-start;
}


/* =========================================================
   КАРТОЧКА МОЕГО СООБЩЕНИЯ
   ========================================================= */

.my-card-body {
    width: fit-content;

    max-width: 100%;

    padding: 13px 17px;

    background:
        linear-gradient(
            135deg,
            rgba(255, 215, 0, 0.20),
            rgba(255, 152, 0, 0.08)
        );

    border: 1px solid rgba(255, 215, 0, 0.65);

    border-radius: 18px 18px 4px 18px;

    box-shadow:
        0 4px 15px rgba(255, 215, 0, 0.08);
}


/* =========================================================
   КАРТОЧКА ПОЛУЧЕННОГО СООБЩЕНИЯ
   ========================================================= */

.card-body {
    width: fit-content;

    max-width: 100%;

    padding: 13px 17px;

    background:
        linear-gradient(
            135deg,
            rgba(0, 229, 255, 0.09),
            rgba(255, 255, 255, 0.025)
        );

    border: 1px solid rgba(0, 229, 255, 0.35);

    border-radius: 18px 18px 18px 4px;

    box-shadow:
        0 4px 15px rgba(0, 0, 0, 0.25);
}


/* =========================================================
   ЗАГОЛОВОК СООБЩЕНИЯ
   ========================================================= */

.card-title {
    margin: 0 0 7px;

    color: #00e5ff;

    font-size: 0.95rem;

    font-weight: 700;
}


.my-card-body .card-title {
    color: gold;
}


.card-title small {
    margin-left: 8px;

    color: #888;

    font-size: 0.78rem;

    font-weight: 400;
}


.message-status {
    margin-left: 5px;
}


/* =========================================================
   ТЕКСТ СООБЩЕНИЯ
   ========================================================= */

.card-text {
    margin: 0;

    color: #f2f2f2;

    font-size: 1rem;

    line-height: 1.5;

    word-break: break-word;

    overflow-wrap: anywhere;
}


/* =========================================================
   REPLY PREVIEW В СООБЩЕНИИ
   ========================================================= */

.reply-preview {
    display: flex;

    align-items: center;

    gap: 9px;

    width: 100%;

    box-sizing: border-box;

    margin-bottom: 7px;

    padding: 8px 11px;

    background:
        rgba(0, 229, 255, 0.06);

    border-left: 3px solid #00e5ff;

    border-radius: 8px;

    color: #aaa;

    font-size: 0.85rem;
}


.reply-indicator {
    color: #00e5ff;
}


.reply-content {
    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;
}


/* =========================================================
   ПРЕВЬЮ ОТВЕТА НАД ПОЛЕМ
   ========================================================= */

.reply-preview-form {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-top: 15px;

    padding: 12px 15px;

    background:
        linear-gradient(
            135deg,
            rgba(0, 229, 255, 0.08),
            rgba(255, 215, 0, 0.04)
        );

    border: 1px solid rgba(0, 229, 255, 0.35);

    border-radius: 14px;
}


.reply-info {
    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 0;

    overflow: hidden;
}


.reply-icon {
    flex-shrink: 0;

    color: #00e5ff;

    font-size: 1rem;
}


.reply-to-name {
    flex-shrink: 0;

    color: gold;

    font-weight: 700;
}


.reply-to-text {
    min-width: 0;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

    color: #aaa;
}


.cancel-reply {
    flex-shrink: 0;

    width: 32px;

    height: 32px;

    border: 1px solid rgba(255, 82, 82, 0.5);

    border-radius: 50%;

    background: rgba(255, 82, 82, 0.08);

    color: #ff6b6b;

    cursor: pointer;

    font-size: 1rem;

    transition:
        background 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease;
}


.cancel-reply:hover {
    background: #ff5252;

    color: #fff;

    box-shadow:
        0 0 12px rgba(255, 82, 82, 0.35);
}


/* =========================================================
   ПОЛЕ ВВОДА
   ========================================================= */

#messageForm {
    margin-top: 18px;
}


.input-group {
    display: flex;

    align-items: stretch;

    gap: 10px;
}


.input-wrapper {
    flex: 1;

    min-width: 0;
}


#messageInput {
    display: block;

    width: 100%;

    min-height: 52px;

    max-height: 180px;

    padding: 14px 18px;

    box-sizing: border-box;

    resize: none;

    overflow-y: auto;

    background:
        linear-gradient(
            145deg,
            #15161f,
            #1c1d25
        );

    color: #fff;

    border: 1px solid rgba(255, 215, 0, 0.55);

    border-radius: 27px;

    outline: none;

    font-family: inherit;

    font-size: 1rem;

    line-height: 1.4;

    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease,
        background 0.3s ease;
}


#messageInput::placeholder {
    color: #777;
}


#messageInput:hover {
    border-color: rgba(255, 215, 0, 0.85);
}


#messageInput:focus {
    border-color: gold;

    background:
        linear-gradient(
            145deg,
            #191a25,
            #22242e
        );

    box-shadow:
        0 0 0 2px rgba(255, 215, 0, 0.08),
        0 0 15px rgba(255, 215, 0, 0.25);
}


/* =========================================================
   КНОПКА ОТПРАВКИ
   ========================================================= */

.send-btn {
    flex-shrink: 0;

    width: 54px;

    min-height: 52px;

    border: 1px solid #ffe45c;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #ffd700,
            #ffb300,
            #ff9800
        );

    color: #0b0c18;

    cursor: pointer;

    font-size: 1.2rem;

    box-shadow:
        0 5px 18px rgba(255, 215, 0, 0.20);

    transition:
        transform 0.25s ease,
        filter 0.25s ease,
        box-shadow 0.25s ease;
}


.send-btn:hover {
    filter: brightness(1.1);

    transform: translateY(-2px);

    box-shadow:
        0 8px 25px rgba(255, 215, 0, 0.45),
        0 0 18px rgba(255, 215, 0, 0.25);
}


.send-btn:active {
    transform: scale(0.95);
}
.send-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 0;
    line-height: 1;
}
.send-btn > i {
    display: block;
    line-height: 1;
    transition: opacity .15s ease;
}
.send-btn.is-loading {
    cursor: wait;
    filter: brightness(.92);
    transform: none;
}
.send-btn.is-loading > i { opacity: 0; }
.send-btn.is-loading::after {
    content: "";
    position: absolute;
    width: 23px;
    height: 23px;
    border: 3px solid rgba(11, 12, 24, .28);
    border-top-color: #0b0c18;
    border-right-color: #0b0c18;
    border-radius: 50%;
    animation: composer-send-spin .7s linear infinite;
}
@keyframes composer-send-spin {
    to { transform: rotate(360deg); }
}
.send-btn:disabled { cursor: not-allowed; opacity: .8; }


/* =========================================================
   ДОПОЛНИТЕЛЬНЫЕ КНОПКИ
   ========================================================= */

.additional-buttons {
    display: flex;

    justify-content: center;

    flex-wrap: wrap;

    gap: 12px;

    margin-top: 22px;
}


.chat-btn {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 44px;

    padding: 9px 20px;

    box-sizing: border-box;

    background:
        linear-gradient(
            135deg,
            rgba(0, 229, 255, 0.10),
            rgba(255, 215, 0, 0.06)
        );

    color: #00e5ff;

    border: 1px solid rgba(0, 229, 255, 0.65);

    border-radius: 22px;

    text-decoration: none;

    font-size: 0.95rem;

    font-weight: 600;

    transition:
        background 0.25s ease,
        color 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.25s ease,
        transform 0.25s ease;
}


.chat-btn:hover {
    background:
        linear-gradient(
            135deg,
            #00e5ff,
            #00bcd4
        );

    color: #071018;

    border-color: #00e5ff;

    box-shadow:
        0 0 15px rgba(0, 229, 255, 0.45);

    transform: translateY(-2px);

    text-decoration: none;
}


/* =========================================================
   СКРОЛЛБАР
   ========================================================= */

.chat-messages::-webkit-scrollbar {
    width: 9px;
}


.chat-messages::-webkit-scrollbar-track {
    background: #0c0d14;

    border-radius: 10px;
}


.chat-messages::-webkit-scrollbar-thumb {
    background:
        linear-gradient(
            #555,
            #888
        );

    border-radius: 10px;

    border: 2px solid #0c0d14;
}


.chat-messages::-webkit-scrollbar-thumb:hover {
    background:
        linear-gradient(
            gold,
            #ff9800
        );
}


/* =========================================================
   АДАПТИВНОСТЬ
   ========================================================= */

@media (max-width: 700px) {

    .chat-container {
        width: calc(100% - 20px);

        margin: 15px auto 30px;

        padding: 18px;

        border-radius: 17px;
    }


    .chat-title {
        font-size: 1.55rem;
    }


    .chat-status {
        font-size: 0.9rem;
    }


    .chat-messages {
        height: 58vh;

        min-height: 400px;

        padding: 12px;
    }


    .message {
        max-width: 88%;
    }


    .my-card-body,
    .card-body {
        padding: 11px 13px;
    }


    .card-title {
        font-size: 0.88rem;
    }


    .card-text {
        font-size: 0.95rem;
    }


    /* =====================================================
       ПОЛЕ ВВОДА + КНОПКА ОТПРАВКИ
       ВСЕГДА В ОДНОЙ СТРОКЕ
       ===================================================== */

    .input-group {
        display: flex;

        flex-direction: row;

        align-items: center;

        flex-wrap: nowrap;

        width: 100%;

        gap: 7px;
    }


    .input-wrapper {
        flex: 1 1 auto;

        min-width: 0;

        width: auto;
    }


    #messageInput {
        display: block;

        width: 100%;

        min-width: 0;

        min-height: 48px;

        padding: 12px 15px;

        box-sizing: border-box;

        font-size: 0.95rem;
    }


    .composer-tools {
        flex: 0 0 44px;
    }

    .composer-tools-toggle {
        width: 44px;
        height: 44px;
        flex-basis: 44px;
    }

    .send-btn {
        flex: 0 0 48px;

        width: 48px;

        height: 48px;

        min-width: 48px;

        min-height: 48px;

        padding: 0;

        display: flex;

        align-items: center;

        justify-content: center;
    }


    .additional-buttons {
        flex-direction: column;
    }


    .chat-btn {
        width: 100%;
    }
}


@media (max-width: 420px) {

    .chat-container {
        width: calc(100% - 12px);

        padding: 13px;
    }


    .chat-title {
        font-size: 1.35rem;
    }


    .chat-messages {
        min-height: 350px;

        height: 55vh;

        padding: 9px;
    }


    .message {
        max-width: 93%;
    }


    .my-card-body,
    .card-body {
        padding: 10px 11px;
    }


    .card-text {
        font-size: 0.9rem;
    }


    .reply-preview-form {
        padding: 9px 11px;
    }


    .reply-to-text {
        display: none;
    }
}


/* =========================================================
   ПОДСКАЗКИ @УПОМИНАНИЙ
   ========================================================= */
.input-wrapper { position: relative; }
.chat-mention-suggestions {
    position: absolute;
    z-index: 30;
    left: 0;
    right: 0;
    bottom: calc(100% + 8px);
    display: grid;
    gap: 4px;
    max-height: 220px;
    overflow-y: auto;
    padding: 7px;
    background: #111827;
    border: 1px solid rgba(0, 229, 255, .35);
    border-radius: 12px;
    box-shadow: 0 10px 28px rgba(0,0,0,.4);
}
.chat-mention-suggestions[hidden] { display: none; }
.chat-mention-suggestion {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 8px 10px;
    color: #e5e7eb;
    text-align: left;
    background: transparent;
    border: 0;
    border-radius: 7px;
    cursor: pointer;
}
.chat-mention-suggestion:hover {
    color: gold;
    background: rgba(0, 229, 255, .08);
}
.chat-mention-suggestion small { color: #00e5ff; }

/* =========================================================
   РЕАКЦИИ НА СООБЩЕНИЯ
   ========================================================= */
.message-reactions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    position: relative;
    margin-top: 5px;
}

.chat-pinned-messages {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin: 8px 0 12px;
    padding: 9px 12px;
    border: 1px solid rgba(255, 215, 0, .28);
    border-left: 3px solid #ffd700;
    border-radius: 10px;
    background: rgba(255, 215, 0, .045);
}
.chat-pinned-messages[hidden] { display: none; }
.pinned-messages-heading {
    color: #ffd700;
    font-size: .78rem;
    white-space: nowrap;
}
.pinned-message-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 4px 8px;
    border: 1px solid rgba(0, 229, 255, .22);
    border-radius: 8px;
    color: #dbeafe;
    background: rgba(0, 229, 255, .055);
    cursor: pointer;
    font-size: .78rem;
}
.pinned-message-item small { color: #94a3b8; }
.pinned-message-jump {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-width: 0;
    flex: 1;
    padding: 4px 2px;
    border: 0;
    color: inherit;
    background: transparent;
    text-align: left;
    cursor: pointer;
}
.pinned-message-jump > span:nth-child(2) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pinned-message-actions {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pinned-message-actions button {
    min-height: 28px;
    padding: 3px 7px;
    border: 1px solid rgba(0, 229, 255, .3);
    border-radius: 7px;
    color: #00e5ff;
    background: rgba(0, 229, 255, .06);
    cursor: pointer;
}
.pinned-message-actions button:disabled { opacity: .4; cursor: not-allowed; }
.pinned-message-unpin {
    border-color: rgba(255, 215, 0, .35) !important;
    color: #ffd700 !important;
}
.pinned-message-item.is-dragging { opacity: .4; }
@media (max-width: 600px) {
    .pinned-message-item { align-items: stretch; }
    .pinned-message-jump { min-width: 0; }
    .pinned-message-actions { flex-shrink: 0; }
}
.message-highlight {
    outline: 2px solid rgba(0, 229, 255, .8);
    outline-offset: 3px;
    transition: outline-color .3s ease;
}
@media (max-width: 600px) {
    .chat-pinned-messages { align-items: flex-start; }
    .pinned-message-item { width: 100%; justify-content: flex-start; }
}
.message-read-status {
    display: block;
    margin-top: 3px;
    color: #00e5ff;
    font-size: .72rem;
    line-height: 1;
    text-align: right;
    letter-spacing: .04em;
}
.add-reaction,
.reaction-pill,
.reaction-choice {
    color: #dbeafe;
    background: rgba(0, 229, 255, .07);
    border: 1px solid rgba(0, 229, 255, .24);
    border-radius: 14px;
    padding: 3px 8px;
    cursor: pointer;
    font-size: .85rem;
}
.reaction-pill.active {
    color: gold;
    border-color: rgba(255, 215, 0, .65);
    background: rgba(255, 215, 0, .1);
}
.reaction-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    position: absolute;
    z-index: 20;
    left: 0;
    bottom: calc(100% + 5px);
    padding: 7px;
    background: #111827;
    border: 1px solid rgba(255, 215, 0, .4);
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,.45);
}
.reaction-picker[hidden] { display: none; }
.reaction-choice { padding: 5px 7px; font-size: 1rem; }

/* =========================================================
   ЗАШИФРОВАННЫЕ МЕДИАФАЙЛЫ
   ========================================================= */
.chat-media-message {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
}
.chat-media-load,
.chat-media-download {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #00e5ff;
    background: rgba(0, 229, 255, .08);
    border: 1px solid rgba(0, 229, 255, .35);
    border-radius: 18px;
    padding: 8px 13px;
    text-decoration: none;
    cursor: pointer;
}
.chat-media-message small {
    color: #aeb7c5;
    overflow-wrap: anywhere;
}
.chat-media-image {
    display: block;
    max-width: min(100%, 420px);
    max-height: 360px;
    object-fit: contain;
    border-radius: 10px;
}
.chat-media-video {
    display: block;
    width: min(100%, 420px);
    max-height: 360px;
    border-radius: 10px;
    background: #000;
}

.chat-video-note { display: flex; justify-content: flex-start; }
.chat-video-note-player {
    display: block;
    width: 220px;
    height: 220px;
    max-width: min(64vw, 220px);
    max-height: min(64vw, 220px);
    aspect-ratio: 1;
    object-fit: cover;
    border: 2px solid rgba(0, 229, 255, .55);
    border-radius: 50%;
    background: #000;
    box-shadow: 0 0 18px rgba(0, 229, 255, .12);
}
@media (max-width: 600px) {
    .chat-video-note-player { width: 190px; height: 190px; }
}

.chat-poll-message {
    min-width: min(280px, 100%);
    display: flex;
    flex-direction: column;
    gap: 10px;
    color: #f5f7fb;
}
.chat-poll-question { color: #ffd700; font-weight: 700; font-size: 1rem; }
.chat-poll-option {
    position: relative;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 9px 11px;
    border: 1px solid rgba(0, 229, 255, .32);
    border-radius: 9px;
    color: #e8f2ff;
    background: rgba(0, 229, 255, .055);
    text-align: left;
    cursor: pointer;
}
.chat-poll-option.selected { border-color: #ffd700; color: #ffd700; }
.chat-poll-option:disabled { cursor: default; }
.chat-poll-progress {
    height: 4px;
    overflow: hidden;
    border-radius: 5px;
    background: rgba(255,255,255,.1);
}
.chat-poll-progress span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #00e5ff, #ffd700);
}
.chat-poll-voters { color: #9ca3af; font-size: .75rem; }
.chat-poll-footer { display: flex; justify-content: space-between; gap: 8px; color: #9ca3af; font-size: .75rem; }
.chat-poll-submit, .chat-poll-close {
    padding: 7px 12px;
    border: 1px solid rgba(255, 215, 0, .4);
    border-radius: 8px;
    color: #ffd700;
    background: rgba(255, 215, 0, .06);
    cursor: pointer;
}
.chat-poll-modal {
    position: fixed;
    inset: 0;
    z-index: 99990;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(0,0,0,.78);
}
.chat-poll-modal[hidden] { display: none; }
.chat-poll-form {
    width: min(100%, 480px);
    display: grid;
    gap: 10px;
    padding: 22px;
    border: 1px solid rgba(255,215,0,.55);
    border-left: 4px solid #00e5ff;
    border-radius: 14px;
    color: #fff;
    background: #101421;
    box-shadow: 0 16px 50px rgba(0,0,0,.5);
}
.chat-poll-form h3 { margin: 0 0 6px; color: #ffd700; }
.chat-poll-form input[type="text"], .chat-poll-form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid rgba(0,229,255,.35);
    border-radius: 8px;
    color: #fff;
    background: #090d17;
}
.chat-poll-checkbox { display: flex; align-items: center; gap: 8px; color: #cbd5e1; }
.chat-poll-form-actions { display: flex; justify-content: flex-end; gap: 8px; }
.chat-poll-form-actions button {
    padding: 8px 13px;
    border: 1px solid rgba(0,229,255,.4);
    border-radius: 8px;
    color: #00e5ff;
    background: rgba(0,229,255,.06);
    cursor: pointer;
}
@media (max-width: 600px) {
    .chat-poll-form { padding: 16px; }
}

.chat-media-image { cursor: zoom-in; }
.chat-media-viewer {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 56px 20px 20px;
    background: rgba(0, 0, 0, .94);
    backdrop-filter: blur(10px);
}
.chat-media-viewer-content {
    display: block;
    max-width: min(96vw, 1600px);
    max-height: calc(100vh - 90px);
    object-fit: contain;
    border: 1px solid rgba(0, 229, 255, .35);
    border-radius: 10px;
    box-shadow: 0 0 35px rgba(0, 229, 255, .12);
}
.chat-media-viewer-content:where(iframe) {
    width: min(96vw, 1600px);
    height: calc(100vh - 90px);
    background: #fff;
}
.chat-media-viewer-close {
    position: absolute;
    top: 14px;
    right: 18px;
    width: 44px;
    height: 44px;
    border: 1px solid rgba(255, 215, 0, .55);
    border-radius: 50%;
    color: #ffd700;
    background: rgba(15, 17, 28, .9);
    font-size: 30px;
    line-height: 1;
    cursor: pointer;
}
body.chat-media-viewer-open { overflow: hidden; }
@media (max-width: 600px) {
    .chat-media-viewer { padding: 54px 8px 8px; }
    .chat-media-viewer-content { max-width: 100%; max-height: calc(100vh - 70px); }
}

/* =========================================================
   ГОЛОСОВЫЕ СООБЩЕНИЯ
   ========================================================= */
.voice-record-btn {
    flex: 0 0 52px;
    min-width: 52px;
    min-height: 52px;
    color: #00e5ff;
    background: rgba(0, 229, 255, 0.08);
    border: 1px solid rgba(0, 229, 255, 0.55);
    border-radius: 50%;
    cursor: pointer;
    transition: color .2s ease, background .2s ease, box-shadow .2s ease;
}
.voice-record-btn:hover {
    color: #0b0c18;
    background: #00e5ff;
    box-shadow: 0 0 14px rgba(0, 229, 255, .3);
}
.voice-record-btn.recording {
    color: #fff;
    background: #c62828;
    border-color: #ff5252;
    animation: voice-record-pulse 1s infinite;
}

/* Компактное меню дополнительных действий */
.composer-tools {
    position: relative;
    flex: 0 0 52px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.composer-tools-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    flex: 0 0 52px;
    color: #00e5ff;
    background: rgba(0, 229, 255, .08);
    border: 1px solid rgba(0, 229, 255, .55);
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    transition: transform .2s ease, background .2s ease, color .2s ease;
}
.composer-tools-toggle:hover,
.composer-tools-toggle[aria-expanded="true"] {
    color: #0b0c18;
    background: #00e5ff;
    transform: rotate(45deg);
}
.composer-tools-menu {
    position: absolute;
    z-index: 100;
    right: 0;
    bottom: calc(100% + 10px);
    display: grid;
    gap: 5px;
    width: min(270px, calc(100vw - 48px));
    padding: 8px;
    background: #101624;
    border: 1px solid rgba(0, 229, 255, .38);
    border-radius: 14px;
    box-shadow: 0 14px 38px rgba(0, 0, 0, .48), 0 0 18px rgba(0, 229, 255, .08);
}
.composer-tools-menu[hidden] { display: none; }
.composer-tool-btn {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    min-height: 42px;
    padding: 8px 11px;
    color: #e5edf5;
    text-align: left;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 9px;
    cursor: pointer;
    font: inherit;
    font-size: .9rem;
}
.composer-tool-btn > i {
    width: 19px;
    flex: 0 0 19px;
    color: #00e5ff;
    text-align: center;
    font-size: 1rem;
}
.composer-tool-btn:hover:not(:disabled) {
    color: #ffd700;
    background: rgba(0, 229, 255, .08);
    border-color: rgba(0, 229, 255, .2);
}
.composer-tool-btn:disabled,
.composer-tools-toggle:disabled {
    opacity: .45;
    cursor: not-allowed;
}
@keyframes voice-record-pulse {
    50% { box-shadow: 0 0 0 7px rgba(255, 82, 82, .16); }
}
.chat-voice-message {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    min-width: 210px;
}
.chat-voice-load {
    color: #00e5ff;
    background: rgba(0, 229, 255, .08);
    border: 1px solid rgba(0, 229, 255, .35);
    border-radius: 18px;
    padding: 8px 13px;
    cursor: pointer;
}
.chat-voice-message small {
    color: #aeb7c5;
}
.chat-voice-message audio {
    width: min(100%, 320px);
    height: 38px;
}
@media (max-width: 520px) {
    .voice-record-btn { flex-basis: 46px; min-width: 46px; min-height: 46px; }
}

/* =========================================================
   КАРТОЧКА ПРЕДПРОСМОТРА ССЫЛКИ
   ========================================================= */
.chat-link-preview {
    display: flex;
    gap: 10px;
    max-width: 420px;
    margin-top: 10px;
    padding: 9px;
    color: #e5e7eb;
    text-decoration: none;
    background: rgba(0, 229, 255, 0.045);
    border: 1px solid rgba(0, 229, 255, 0.24);
    border-left: 3px solid #00e5ff;
    border-radius: 10px;
}
.chat-link-preview:hover {
    border-color: rgba(255, 215, 0, 0.55);
}
.chat-link-preview img {
    width: 76px;
    height: 76px;
    flex: 0 0 76px;
    object-fit: cover;
    border-radius: 7px;
    background: #10131d;
}
.chat-link-preview-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}
.chat-link-preview-content small {
    color: #00e5ff;
    overflow-wrap: anywhere;
}
.chat-link-preview-content strong {
    color: gold;
    font-size: 0.9rem;
}
.chat-link-preview-content span {
    color: #aeb7c5;
    font-size: 0.82rem;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
@media (max-width: 520px) {
    .chat-link-preview { max-width: 100%; }
    .chat-link-preview img { width: 64px; height: 64px; flex-basis: 64px; }
}

/* =========================================================
   УЧАСТНИКИ ЧАТА
   ========================================================= */
.chat-members-panel {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0 0 14px;
    color: #d1d5db;
}
.chat-members-panel details {
    flex: 1 1 260px;
    min-width: 0;
}
.chat-members-panel summary {
    color: #00e5ff;
    cursor: pointer;
    font-weight: 700;
}
.chat-members-list {
    display: grid;
    gap: 7px;
    margin-top: 10px;
}
.chat-member {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 10px;
    background: rgba(0, 229, 255, 0.045);
    border: 1px solid rgba(0, 229, 255, 0.14);
    border-radius: 10px;
}
.chat-member-name { color: #fff; font-weight: 600; }
.chat-member-role { color: gold; font-size: 0.8rem; }
.chat-member-role-select,
.chat-participant-modal select {
    min-height: 32px;
    color: #fff;
    background: #151923;
    border: 1px solid rgba(255, 215, 0, 0.35);
    border-radius: 8px;
    padding: 4px 8px;
}
.chat-member-remove {
    color: #ff8a8a;
    background: transparent;
    border: 1px solid rgba(255, 82, 82, 0.35);
    border-radius: 8px;
    padding: 5px 9px;
    cursor: pointer;
}
.chat-participant-modal {
    flex: 1 1 100%;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 12px;
    background: rgba(11, 12, 24, 0.98);
    border: 1px solid rgba(255, 215, 0, 0.4);
    border-radius: 12px;
}
.chat-participant-modal[hidden] { display: none; }
.chat-participant-modal-actions { display: flex; gap: 8px; }
.chat-participant-modal-actions button {
    color: gold;
    background: rgba(255, 215, 0, 0.07);
    border: 1px solid rgba(255, 215, 0, 0.35);
    border-radius: 8px;
    padding: 7px 12px;
    cursor: pointer;
}

/* =========================================================
   НАВИГАЦИЯ ПО ТЕМАМ
   ========================================================= */
.chat-topics-bar {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    overflow-x: auto;
    padding: 0 0 14px;
    margin-bottom: 14px;
    scrollbar-width: thin;
}
.chat-topic-tab,
.chat-topic-create {
    flex: 0 0 auto;
    min-height: 36px;
    padding: 7px 14px;
    color: #cbd5e1;
    background: rgba(0, 229, 255, 0.06);
    border: 1px solid rgba(0, 229, 255, 0.25);
    border-radius: 18px;
    cursor: pointer;
}
.chat-topic-tab.active {
    color: #0b0c18;
    background: linear-gradient(135deg, #ffd700, #ffb300);
    border-color: gold;
    font-weight: 700;
}
.chat-topic-create {
    color: gold;
    border-color: rgba(255, 215, 0, 0.45);
}
@media (max-width: 700px) {
    .chat-topics-bar {
        margin-right: -8px;
        padding-bottom: 10px;
    }
    .chat-topic-tab,
    .chat-topic-create {
        min-height: 34px;
        padding: 6px 12px;
        font-size: 0.88rem;
    }
}

/* =========================================================
   БЕЗОПАСНЫЕ ССЫЛКИ И УПОМИНАНИЯ
   ========================================================= */
.chat-link {
    color: #00e5ff;
    text-decoration: underline;
    text-decoration-color: rgba(0, 229, 255, 0.45);
    overflow-wrap: anywhere;
}
.chat-link:hover {
    color: gold;
    text-decoration-color: gold;
}
.chat-mention {
    display: inline-block;
    color: gold;
    font-weight: 700;
    background: rgba(255, 215, 0, 0.09);
    border: 1px solid rgba(255, 215, 0, 0.22);
    border-radius: 5px;
    padding: 0 4px;
}


.telegram-chat-layout {
    display: grid;
    grid-template-columns: 310px minmax(0, 1fr);
    gap: 14px;
    width: min(100%, 1600px);
    margin: 12px auto 28px;
    padding: 0 14px;
    box-sizing: border-box;
    align-items: start;
}
.telegram-chat-sidebar {
    position: sticky;
    top: 78px;
    display: flex;
    flex-direction: column;
    min-width: 0;
    height: calc(100vh - 96px);
    max-height: 900px;
    overflow: hidden;
    border: 1px solid rgba(0,229,255,.28);
    border-left: 3px solid #00e5ff;
    border-radius: 16px;
    background: linear-gradient(150deg,#0c1220,#111827 58%,#0b0c18);
    box-shadow: 0 12px 35px rgba(0,0,0,.4);
}
.telegram-sidebar-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 16px 12px;
    color: #ffd700;
    border-bottom: 1px solid rgba(255,215,0,.18);
}
.telegram-sidebar-heading span { color: #00e5ff; font-size: .72rem; letter-spacing: .14em; }
.telegram-sidebar-heading a { color: #00e5ff; text-decoration: none; font-size: 1.25rem; }
.telegram-sidebar-stories .chat-story-add,
.telegram-sidebar-stories .chat-story-card {
    display: flex;
    flex: 0 0 58px;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    padding: 0;
    border: 0;
    color: #dbe4f0;
    background: transparent;
    font-size: .62rem;
    cursor: pointer;
}
.telegram-sidebar-stories .chat-story-add-avatar,
.telegram-sidebar-stories .chat-story-avatar {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    object-fit: cover;
    overflow: hidden;
    border: 2px solid #00e5ff;
    border-radius: 50%;
    color: #ffd700;
    background: #111827;
}
.telegram-sidebar-stories .chat-story-card.unviewed .chat-story-avatar {
    border: 3px solid transparent;
    background: linear-gradient(#111827,#111827) padding-box, linear-gradient(135deg,#00e5ff,#ffd700) border-box;
}
.telegram-sidebar-stories .chat-story-card.viewed .chat-story-avatar { border-color: #64748b; opacity: .75; }
.telegram-sidebar-stories .chat-story-name { max-width: 58px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
.telegram-sidebar-stories {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    overflow-x: auto;
    padding: 10px 10px 4px;
    border-bottom: 1px solid rgba(255,215,0,.15);
}
.telegram-sidebar-stories .chat-story-add,
.telegram-sidebar-stories .chat-story-card { flex-basis: 58px; font-size: .62rem; }
.telegram-sidebar-stories .chat-story-add-avatar,
.telegram-sidebar-stories .chat-story-avatar { width: 42px; height: 42px; font-size: 1.1rem; }
.telegram-sidebar-stories .chat-story-name { max-width: 58px; }
.telegram-chat-search {
    margin: 12px;
    padding: 10px 12px;
    color: #fff;
    background: #090f1b;
    border: 1px solid rgba(0,229,255,.28);
    border-radius: 10px;
    outline: none;
}
.telegram-chat-search:focus { border-color: #ffd700; box-shadow: 0 0 0 2px rgba(255,215,0,.08); }
.telegram-chat-list { flex: 1; min-height: 0; overflow-y: auto; padding: 0 8px 12px; }
.telegram-chat-row {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 10px 9px;
    color: #e5e7eb;
    text-decoration: none;
    border-bottom: 1px solid rgba(255,255,255,.045);
    border-left: 3px solid transparent;
    border-radius: 9px;
}
.telegram-chat-row:hover { background: rgba(0,229,255,.07); color: #fff; }
.telegram-chat-row.active {
    border-left-color: #ffd700;
    background: linear-gradient(90deg,rgba(255,215,0,.12),rgba(0,229,255,.055));
}
.telegram-chat-avatar {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    object-fit: cover;
    border: 1px solid rgba(0,229,255,.55);
    border-radius: 50%;
    color: #ffd700;
    background: #151b29;
}
.telegram-chat-avatar.saved { border-color: rgba(255,215,0,.6); }
.telegram-chat-row-body { display: flex; flex-direction: column; min-width: 0; flex: 1; gap: 4px; }
.telegram-chat-row-body strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #f8fafc; font-size: .9rem; }
.telegram-chat-row-body small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #94a3b8; font-size: .72rem; }
.telegram-chat-unread {
    display: inline-grid;
    place-items: center;
    min-width: 21px;
    height: 21px;
    padding: 0 5px;
    border-radius: 20px;
    color: #08111d;
    background: #00e5ff;
    font-size: .7rem;
    font-weight: 800;
}
.telegram-new-chat {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 8px 12px 12px;
    padding: 11px;
    color: #ffd700;
    border: 1px solid rgba(255,215,0,.4);
    border-radius: 10px;
    text-decoration: none;
    background: rgba(255,215,0,.05);
}
.telegram-chat-main { min-width: 0; }
.telegram-chat-layout .chat-container {
    width: 100%;
    max-width: none;
    margin: 0;
    min-width: 0;
}
.telegram-mobile-back { display: none; }
@media (max-width: 980px) {
    .telegram-chat-layout { grid-template-columns: 260px minmax(0,1fr); gap: 10px; padding: 0 8px; }
    .telegram-chat-sidebar { height: calc(100vh - 90px); }
    .telegram-chat-layout .chat-container { padding: 20px; }
}
@media (max-width: 720px) {
    .telegram-chat-layout { display: block; margin: 0; padding: 0; }
    .telegram-chat-sidebar { display: none; }
    .telegram-chat-layout .chat-container {
        width: 100%;
        margin: 0;
        padding: 14px 10px 20px;
        border-radius: 0;
        border-left: 0;
        border-right: 0;
    }
    .telegram-mobile-back {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin: 0 0 12px;
        color: #00e5ff;
        text-decoration: none;
        font-size: .88rem;
    }
    .chat-title { font-size: 1.35rem; }
}


/* Навигация и дерево тем в боковом меню мессенджера. */
.telegram-sidebar-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
    margin: 10px 0 14px;
}
.telegram-sidebar-actions a {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 9px 10px;
    color: #00e5ff;
    text-decoration: none;
    font-size: .82rem;
    border: 1px solid rgba(0,229,255,.22);
    border-radius: 9px;
    background: rgba(0,229,255,.035);
}
.telegram-sidebar-actions a:hover { color: #ffd700; border-color: rgba(255,215,0,.5); }
.telegram-chat-topics { margin: 3px 8px 9px 58px; color: #aeb9ca; font-size: .78rem; }
.telegram-chat-topics summary, .chat-topics-mobile summary { cursor: pointer; padding: 5px 2px; color: #00e5ff; }
.telegram-chat-topic-links { display: grid; gap: 3px; padding: 4px 0 6px 10px; }
.telegram-chat-topic-links a, .chat-topics-mobile a {
    display: block; padding: 5px 8px; color: #c4cedd; text-decoration: none;
    border-left: 1px solid rgba(0,229,255,.25); border-radius: 0 6px 6px 0;
}
.telegram-chat-topic-links a:hover, .chat-topics-mobile a:hover { color: #ffd700; background: rgba(255,215,0,.05); }
.chat-topics-sidebar { display: flex; flex-direction: column; align-items: stretch; gap: 3px; margin: 4px 0 7px; padding: 4px 0; border: 0; background: transparent; }
.chat-topics-sidebar .chat-topic-tab, .chat-topics-sidebar .chat-topic-create {
    width: 100%; justify-content: flex-start; text-align: left; padding: 7px 9px;
}
.chat-topics-mobile { display: none; margin: 8px 0 12px; padding: 7px 10px; border: 1px solid rgba(0,229,255,.25); border-radius: 9px; }
@media (max-width: 700px) { .chat-topics-mobile { display: block; } }

.scheduled-message-modal {
    position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center;
    padding: 18px; background: rgba(0,0,0,.78);
}
.scheduled-message-modal[hidden] { display: none; }
.scheduled-message-panel {
    width: min(100%, 520px); max-height: 85vh; overflow: auto; padding: 22px;
    color: #e8edf5; background: linear-gradient(145deg,#0b0c18,#151a29);
    border: 1px solid rgba(0,229,255,.45); border-radius: 16px;
}
.scheduled-message-panel h3 { color: #ffd700; margin-top: 0; }
.scheduled-message-panel label { display: block; margin: 12px 0 6px; color: #b9c5d6; }
.scheduled-message-panel input {
    width: 100%; box-sizing: border-box; padding: 10px; color: #fff;
    background: #080d17; border: 1px solid rgba(0,229,255,.35); border-radius: 8px;
}
.scheduled-message-panel button {
    padding: 8px 12px; color: #00e5ff; background: rgba(0,229,255,.06);
    border: 1px solid rgba(0,229,255,.35); border-radius: 8px; cursor: pointer;
}
.scheduled-message-row {
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.09);
}
.scheduled-message-row small { display: block; color: #9aa8bc; margin-top: 4px; }
.scheduled-messages-empty { color: #9aa8bc; }
.scheduled-message-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }


.chat-call-toolbar { display:flex; justify-content:center; flex-wrap:wrap; gap:8px; margin:12px 0; }
.chat-call-toolbar button {
    min-height:36px; padding:7px 12px; color:#00e5ff; background:rgba(0,229,255,.06);
    border:1px solid rgba(0,229,255,.4); border-radius:9px; cursor:pointer;
}
.chat-call-modal {
    position:fixed; inset:0; z-index:1300; display:grid; place-items:center;
    padding:18px; background:rgba(0,0,0,.84);
}
.chat-call-modal[hidden] { display:none; }
.chat-call-panel {
    width:min(100%,650px); padding:20px; color:#e8edf5;
    background:linear-gradient(145deg,#0b0c18,#151a29);
    border:1px solid rgba(0,229,255,.45); border-radius:16px; text-align:center;
}
.chat-call-panel h3 { color:#ffd700; margin-top:0; }
.chat-call-status { color:#b7c3d4; min-height:24px; }
.chat-call-videos { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
.chat-call-videos video { width:100%; max-height:260px; background:#05070c; border-radius:10px; }
.chat-call-actions { display:flex; justify-content:center; flex-wrap:wrap; gap:8px; margin-top:14px; }
.chat-call-actions button {
    padding:9px 14px; color:#00e5ff; background:rgba(0,229,255,.06);
    border:1px solid rgba(0,229,255,.4); border-radius:8px; cursor:pointer;
}
.chat-call-actions button.danger { color:#ff9b9b; border-color:rgba(255,100,100,.5); }
@media(max-width:560px) { .chat-call-videos { grid-template-columns:1fr; } }


.chat-meeting-row {
    display:flex; flex-direction:column; align-items:flex-start; gap:7px;
    padding:12px 0; border-bottom:1px solid rgba(255,255,255,.09);
}
.chat-meeting-row strong { color:#ffd700; }
.chat-meeting-row small { color:#9aa8bc; }
.chat-meeting-row button {
    padding:7px 11px; color:#00e5ff; background:rgba(0,229,255,.05);
    border:1px solid rgba(0,229,255,.35); border-radius:8px; cursor:pointer;
}


.group-call-modal {
    position: fixed; inset: 0; z-index: 1350; display: grid; place-items: center;
    padding: 16px; background: rgba(0,0,0,.88);
}
.group-call-modal[hidden] { display: none; }
.group-call-panel {
    width: min(100%, 980px); max-height: 92dvh; overflow: auto; padding: 20px;
    color: #e8edf5; background: linear-gradient(145deg,#0b0c18,#151a29);
    border: 1px solid rgba(255,215,0,.5); border-radius: 16px;
    box-shadow: 0 12px 50px rgba(0,0,0,.55);
}
.group-call-panel h3 { margin: 0 0 8px; color: #ffd700; }
.group-call-status { min-height: 22px; color: #aebbd0; }
.group-call-participants {
    display: grid; grid-template-columns: repeat(auto-fit,minmax(min(100%,260px),1fr));
    gap: 12px; min-height: 120px;
}
.group-call-tile {
    position: relative; min-width: 0; overflow: hidden; aspect-ratio: 16 / 9;
    background: #05070c; border: 1px solid rgba(0,229,255,.25); border-radius: 12px;
}
.group-call-tile video { display: block; width: 100%; height: 100%; object-fit: contain; }
.group-call-tile-name {
    position: absolute; left: 9px; bottom: 8px; padding: 4px 8px;
    color: #fff; background: rgba(0,0,0,.65); border-radius: 6px; font-size: .82rem;
}
.group-call-actions {
    display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin-top: 16px;
}
.group-call-actions button {
    min-height: 40px; padding: 8px 13px; color: #00e5ff;
    background: rgba(0,229,255,.06); border: 1px solid rgba(0,229,255,.4);
    border-radius: 9px; cursor: pointer;
}
.group-call-actions button.danger { color: #ff9b9b; border-color: rgba(255,100,100,.5); }
@media(max-width:560px) {
    .group-call-panel { padding: 14px; }
    .group-call-participants { grid-template-columns: 1fr; }
}

/* =========================================================
   КОМПАКТНАЯ ОБЛАСТЬ ЧАТА: ВЫСОТА ОКНА, БЕЗ ВНЕШНЕЙ ПРОКРУТКИ
   ========================================================= */
.telegram-chat-layout {
    height: calc(100dvh - 88px);
    min-height: 360px;
    margin: 8px auto;
    align-items: stretch;
    overflow: hidden;
}
.telegram-chat-sidebar {
    position: relative;
    top: auto;
    height: 100%;
    max-height: none;
}
.telegram-chat-main {
    height: 100%;
    min-height: 0;
    overflow: hidden;
}
.telegram-chat-layout .chat-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    padding: 12px 14px;
    overflow: hidden;
}
.chat-header {
    flex: 0 0 auto;
    margin-bottom: 7px;
    padding-bottom: 7px;
    text-align: left;
}
.chat-title { font-size: 1.35rem; }
.chat-status { margin-top: 2px; font-size: .82rem; }
.chat-call-toolbar { justify-content: flex-start; gap: 6px; margin: 6px 0; }
.chat-call-toolbar button { min-height: 32px; padding: 5px 9px; font-size: .82rem; }
.chat-mute-controls { justify-content: flex-start; gap: 6px; margin-top: 6px; }
.chat-mute-controls select, .chat-mute-controls button { min-height: 32px; padding: 5px 8px; font-size: .8rem; }
.chat-mute-status { flex-basis: auto; font-size: .74rem; }
.chat-message-search { margin-top: 6px; }
.chat-members-panel { flex: 0 0 auto; margin: 0 0 6px; }
.chat-messages {
    flex: 1 1 auto;
    height: auto;
    min-height: 0;
    padding: 12px;
}
#messageForm { flex: 0 0 auto; margin-top: 7px; }
#messageInput { min-height: 46px; max-height: 120px; padding-right: 52px; }
.chat-person-heading { min-width: 0; }
.chat-profile-trigger {
    width: fit-content;
    max-width: 100%;
    padding: 0;
    border: 0;
    color: inherit;
    background: transparent;
    text-align: left;
    cursor: pointer;
}
.chat-profile-trigger:hover .chat-title { color: #fff; }
.chat-profile-hint { display: block; margin-top: 1px; color: #00e5ff; font-size: .68rem; opacity: .8; }
.chat-peer-profile-modal {
    position: fixed;
    inset: 0;
    z-index: 140000;
    display: grid;
    place-items: center;
    padding: 14px;
    background: rgba(0,0,0,.78);
}
.chat-peer-profile-modal[hidden] { display: none; }
.chat-peer-profile-card {
    position: relative;
    width: min(100%, 460px);
    max-height: min(86dvh, 680px);
    overflow-y: auto;
    padding: 22px;
    color: #e8edf5;
    background: linear-gradient(145deg,#0b0c18,#151a29);
    border: 1px solid rgba(0,229,255,.45);
    border-radius: 16px;
    box-shadow: 0 18px 60px rgba(0,0,0,.65);
}
.chat-peer-profile-close {
    position: absolute;
    top: 8px;
    right: 10px;
    width: 34px;
    height: 34px;
    color: #ffd700;
    background: rgba(255,215,0,.07);
    border: 1px solid rgba(255,215,0,.35);
    border-radius: 50%;
    font-size: 22px;
    cursor: pointer;
}
.chat-peer-profile-heading { display: flex; align-items: center; gap: 14px; padding-right: 28px; }
.chat-peer-profile-heading img { width: 66px; height: 66px; flex: 0 0 66px; object-fit: cover; border: 2px solid #00e5ff; border-radius: 50%; }
.chat-peer-profile-heading h3 { margin: 0; color: #ffd700; text-align: left; font-size: 1.2rem; }
.chat-peer-profile-heading p { margin: 4px 0 0; color: #00e5ff; text-align: left; }
.chat-peer-profile-details { display: grid; gap: 11px; margin-top: 18px; }
.chat-peer-profile-details p { display: flex; gap: 10px; margin: 0; color: #dbe4f0; text-align: left; overflow-wrap: anywhere; }
.chat-peer-profile-details p i { width: 18px; flex: 0 0 18px; color: #00e5ff; text-align: center; }
.chat-peer-profile-details a { color: #00e5ff; overflow-wrap: anywhere; }
.chat-peer-profile-details .chat-peer-profile-about { display: block; padding: 12px; white-space: pre-wrap; background: rgba(0,229,255,.05); border-left: 2px solid #00e5ff; border-radius: 8px; }
.chat-peer-profile-empty { color: #94a3b8 !important; }
.chat-peer-profile-link { display: inline-flex; margin-top: 18px; padding: 8px 12px; color: #ffd700; text-decoration: none; border: 1px solid rgba(255,215,0,.4); border-radius: 9px; }
.input-wrapper { position: relative; }
.chat-emoji-toggle {
    position: absolute;
    z-index: 3;
    top: 7px;
    right: 9px;
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    padding: 0;
    color: #ffd700;
    background: rgba(0,229,255,.07);
    border: 1px solid rgba(0,229,255,.28);
    border-radius: 50%;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
}
.chat-emoji-toggle:hover, .chat-emoji-toggle[aria-expanded="true"] { color: #0b0c18; background: #00e5ff; }
.chat-emoji-panel {
    position: absolute;
    z-index: 160000;
    right: 0;
    bottom: calc(100% + 8px);
    width: min(360px, calc(100vw - 24px));
    height: 380px;
    overflow: hidden;
    border: 1px solid rgba(0,229,255,.5);
    border-radius: 13px;
    background: #111827;
    box-shadow: 0 16px 42px rgba(0,0,0,.65);
}
.chat-emoji-panel[hidden] { display: none; }
.chat-emoji-panel emoji-picker {
    width: 100%;
    height: 100%;
    --background: #111827;
    --border-color: rgba(0,229,255,.25);
    --border-radius: 12px;
    --input-border-color: rgba(0,229,255,.3);
    --input-font-color: #f8fafc;
    --input-placeholder-color: #94a3b8;
    --outline-color: #00e5ff;
    --category-emoji-padding: 7px;
    --indicator-color: #ffd700;
    --button-active-background: rgba(0,229,255,.14);
}
@media (max-height: 760px) and (min-width: 721px) {
    .chat-title { font-size: 1.18rem; }
    .chat-header { margin-bottom: 5px; padding-bottom: 5px; }
    .chat-header-avatar { width: 42px; height: 42px; flex-basis: 42px; }
    .chat-call-toolbar { margin: 4px 0; }
    .chat-mute-controls { margin-top: 4px; }
    .chat-members-panel { margin-bottom: 4px; }
    .chat-messages { padding: 9px; }
}
@media (max-width: 720px) {
    .telegram-chat-layout {
        height: calc(100dvh - 54px);
        min-height: 0;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }
    .telegram-chat-main { height: 100%; }
    .telegram-chat-layout .chat-container {
        height: 100%;
        min-height: 0;
        padding: 7px 8px 8px;
        border-radius: 0;
        overflow: hidden;
    }
    .telegram-mobile-back { margin: 0 0 5px; }
    .chat-header { margin-bottom: 5px; padding-bottom: 5px; }
    .chat-title { font-size: 1.05rem; }
    .chat-status { font-size: .72rem; }
    .chat-header-avatar { width: 38px; height: 38px; flex-basis: 38px; }
    .chat-person-heading { gap: 8px; }
    .chat-profile-hint { font-size: .62rem; }
    .chat-call-toolbar { gap: 5px; margin: 4px 0; }
    .chat-call-toolbar button { min-height: 29px; padding: 4px 7px; font-size: .72rem; }
    .chat-mute-controls { gap: 4px; margin-top: 4px; }
    .chat-mute-controls label { font-size: .72rem; }
    .chat-mute-controls select, .chat-mute-controls button { min-height: 29px; padding: 4px 6px; font-size: .72rem; }
    .chat-mute-status { flex-basis: 100%; }
    .chat-message-search { margin-top: 4px; }
    .chat-members-panel { margin-bottom: 4px; }
    .chat-messages { padding: 8px; }
    #messageForm { margin-top: 5px; }
    #messageInput { min-height: 42px; max-height: 90px; padding: 10px 46px 10px 13px; font-size: .9rem; }
    .chat-emoji-toggle { top: 5px; right: 6px; width: 30px; height: 30px; font-size: 18px; }
    .chat-emoji-panel { height: min(360px, 48dvh); }
}
@media (max-width: 420px) {
    .chat-mute-controls label { display: none; }
    .chat-call-toolbar button { font-size: .68rem; }
    .chat-peer-profile-card { padding: 18px; }
}


/* Настройки и медиа канала */
.chat-channel-settings {
    width: min(100%, 520px);
    margin: 8px auto 0;
    text-align: left;
    color: #dbe4ef;
}
.chat-channel-settings > summary {
    color: #00e5ff;
    cursor: pointer;
    font-size: .86rem;
}
.chat-channel-settings form {
    display: grid;
    gap: 9px;
    margin-top: 10px;
    padding: 12px;
    border: 1px solid rgba(0,229,255,.2);
    border-radius: 10px;
    background: rgba(5,12,24,.72);
}
.chat-channel-settings label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .82rem;
}
.chat-channel-settings button {
    justify-self: start;
    padding: 7px 12px;
    border: 1px solid rgba(255,215,0,.4);
    border-radius: 8px;
    color: #ffd700;
    background: rgba(255,215,0,.06);
    cursor: pointer;
}
#channelSettingsStatus { color: #00e5ff; font-size: .8rem; }
#channelHeaderPhoto { background: #10131d; }



/* =========================================================
   UX REFRESH: ПОЛНОЭКРАННЫЙ ЧАТ И КОМПАКТНАЯ ПАНЕЛЬ
   ========================================================= */
body.chat-page {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100dvh;
    min-height: 0;
    overflow: hidden;
}
body.chat-page .mobile-hamburger-header {
    position: relative;
    top: auto;
    flex: 0 0 auto;
}
body.chat-page .telegram-chat-layout {
    flex: 1 1 auto;
    width: 100%;
    height: auto;
    min-height: 0;
    margin: 0;
    overflow: hidden;
}
body.chat-page .telegram-chat-sidebar,
body.chat-page .telegram-chat-main,
body.chat-page .telegram-chat-layout .chat-container {
    min-height: 0;
}
body.chat-page .telegram-chat-main { height: 100%; overflow: hidden; }
body.chat-page .telegram-chat-layout .chat-container {
    height: 100%;
    overflow: hidden;
    padding-bottom: max(10px, env(safe-area-inset-bottom));
}
.chat-container { position: relative; }
.chat-scroll-bottom {
    position: absolute;
    z-index: 25;
    right: 24px;
    bottom: 104px;
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    color: #071018;
    background: linear-gradient(135deg, #00e5ff, #00bcd4);
    border: 1px solid rgba(255,255,255,.25);
    border-radius: 50%;
    box-shadow: 0 5px 18px rgba(0,0,0,.4);
    cursor: pointer;
}
.chat-scroll-bottom[hidden] { display: none; }
.chat-scroll-bottom:hover { background: #ffd700; }
.chat-composer-status {
    min-height: 18px;
    padding: 4px 12px 0;
    color: #00e5ff;
    font-size: .76rem;
}
.chat-composer-status[hidden] { display: none; }
.composer-tools-menu .chat-call-push-settings {
    display: grid;
    gap: 4px;
    padding: 5px 8px;
    border-top: 1px solid rgba(0,229,255,.16);
}
.composer-tools-menu #chat-call-push-toggle {
    padding: 7px 9px;
    color: #a9eaf2;
    text-align: left;
    background: rgba(0,229,255,.05);
    border: 1px solid rgba(0,229,255,.2);
    border-radius: 8px;
    cursor: pointer;
}
.composer-tools-menu #chat-call-push-status { color: #9aa8bc; font-size: .72rem; }
.chat-emoji-panel,
.chat-emoji-panel emoji-picker { max-width: 100%; min-width: 0; }
.chat-emoji-panel emoji-picker { line-height: normal; font-size: 16px; }
.card-text, #messageInput { font-variant-emoji: emoji; }
.card-text { line-height: 1.55; overflow: visible; }
@media (max-width: 720px) {
    body.chat-page .telegram-chat-layout { height: auto; }
    body.chat-page .telegram-chat-sidebar { display: none; }
    body.chat-page .telegram-chat-main { height: 100%; }
    body.chat-page .telegram-chat-layout .chat-container { padding-bottom: max(8px, env(safe-area-inset-bottom)); }
    .chat-scroll-bottom { right: 14px; bottom: 94px; width: 38px; height: 38px; }
    .chat-emoji-panel { right: -4px; width: min(340px, calc(100vw - 16px)); height: min(350px, 48dvh); }
    .chat-emoji-panel emoji-picker { width: 100%; height: 100%; --emoji-size: 1.35rem; }
    .card-text { line-height: 1.6; }
}


.chat-channel-info-trigger {
    display:grid; place-items:center; width:34px; height:34px; flex:0 0 34px;
    color:#00e5ff; background:rgba(0,229,255,.07); border:1px solid rgba(0,229,255,.3);
    border-radius:50%; cursor:pointer;
}
.chat-channel-info-trigger:hover { color:#0b0c18; background:#00e5ff; }
.chat-channel-info-modal {
    position:fixed; inset:0; z-index:140100; display:grid; place-items:center;
    padding:14px; background:rgba(0,0,0,.8);
}
.chat-channel-info-modal[hidden] { display:none; }
.chat-channel-info-card {
    position:relative; width:min(100%,520px); max-height:88dvh; overflow-y:auto;
    padding:22px; color:#e8edf5; background:linear-gradient(145deg,#0b0c18,#151a29);
    border:1px solid rgba(0,229,255,.45); border-radius:16px; box-shadow:0 18px 60px rgba(0,0,0,.65);
}
.chat-channel-info-heading { display:flex; align-items:center; gap:14px; padding-right:30px; }
.chat-channel-info-heading img { width:64px; height:64px; flex:0 0 64px; object-fit:cover; border:2px solid #00e5ff; border-radius:50%; }
.chat-channel-info-heading h3 { margin:0; color:#ffd700; }
.chat-channel-info-heading p { margin:4px 0 0; color:#00e5ff; }
.chat-channel-info-stats { display:flex; flex-wrap:wrap; gap:8px; margin:18px 0; }
.chat-channel-info-stats span { padding:8px 10px; color:#cbd5e1; background:rgba(0,229,255,.05); border:1px solid rgba(0,229,255,.16); border-radius:9px; font-size:.8rem; }
.chat-channel-info-stats strong { color:#ffd700; }
.chat-channel-info-subtitle { margin:14px 0 8px; color:#00e5ff; font-size:.9rem; }
.chat-channel-info-members { display:grid; gap:5px; max-height:220px; overflow:auto; }
.chat-channel-info-members > div { display:flex; justify-content:space-between; gap:10px; padding:7px 9px; background:rgba(255,255,255,.025); border-radius:7px; }
.chat-channel-info-members small { color:#94a3b8; }
.chat-format-toolbar { display:flex; flex-wrap:wrap; gap:5px; margin:0 0 7px; }
.chat-format-toolbar button { min-width:34px; min-height:30px; padding:4px 8px; color:#c8d5e3; background:rgba(0,229,255,.05); border:1px solid rgba(0,229,255,.2); border-radius:7px; cursor:pointer; }
.chat-format-toolbar button:hover { color:#ffd700; border-color:rgba(255,215,0,.45); }
.chat-format-toolbar button[data-format="bold"] { font-weight:800; }
.chat-format-toolbar button[data-format="italic"] { font-style:italic; }
.chat-format-toolbar button[data-format="strike"] { text-decoration:line-through; }
.chat-format-toolbar button[data-format="code"] { font-family:monospace; }


.chat-inline-quote {
    margin: 5px 0;
    padding: 7px 11px;
    color: #c9d8e6;
    background: rgba(0,229,255,.055);
    border-left: 3px solid #00e5ff;
    border-radius: 0 7px 7px 0;
}
.chat-inline-code {
    padding: 2px 5px;
    color: #ffd98a;
    background: rgba(0,0,0,.35);
    border: 1px solid rgba(255,215,0,.15);
    border-radius: 5px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: .9em;
}
.chat-media-image {
    display: block;
    width: auto;
    max-width: min(260px, 100%);
    max-height: 190px;
    object-fit: cover;
    border: 1px solid rgba(0,229,255,.2);
    border-radius: 10px;
}
.chat-media-audio { display:block; width:min(320px,100%); max-width:100%; }
.chat-media-video { display:block; width:min(320px,100%); max-height:220px; border-radius:10px; }
.card-text, #messageInput {
    font-family: inherit;
    font-variant-emoji: emoji;
    line-height: 1.65;
}
.card-text > * { vertical-align: baseline; }
@media (max-width: 720px) {
    .card-text { line-height: 1.7; }
    .chat-media-image { max-width: min(220px,100%); max-height: 160px; }
    .chat-format-toolbar { gap: 4px; }
    .chat-format-toolbar button { min-width: 31px; min-height: 29px; padding: 3px 6px; }
}


.edit-reply-quote {
    flex:0 0 32px; width:32px; height:32px; color:#00e5ff;
    background:rgba(0,229,255,.06); border:1px solid rgba(0,229,255,.28);
    border-radius:50%; cursor:pointer;
}
.edit-reply-quote:hover { color:#0b0c18; background:#00e5ff; }


/* Меню действий не вытягивается длинной колонкой: две компактные колонки */
.composer-tools-menu {
    width: min(420px, calc(100vw - 24px));
    max-height: min(72dvh, 520px);
    grid-template-columns: repeat(2, minmax(0, 1fr));
    overflow-y: auto;
}
.composer-tools-menu .composer-tool-btn {
    min-width: 0;
    min-height: 38px;
    gap: 7px;
    padding: 7px 8px;
    font-size: .78rem;
}
.composer-tools-menu .composer-tool-btn > span {
    min-width: 0;
    overflow-wrap: anywhere;
}
.composer-tools-menu .composer-tool-btn.recording {
    color: #fff;
    background: rgba(198,40,40,.28);
    border-color: rgba(255,82,82,.75);
}
.composer-tools-menu .composer-tool-btn.recording > i { color: #ff8a80; }
.composer-tools-menu .chat-call-push-settings { grid-column: 1 / -1; }
/* Мобильное меню действий: компактная панель над composer,
   не выходит за границы экрана и учитывает нижнюю safe-area. */
@media (max-width: 720px) {
    .composer-tools-menu {
        position: fixed;
        z-index: 160000;
        left: 12px;
        right: 12px;
        bottom: calc(68px + env(safe-area-inset-bottom));
        width: auto;
        max-width: 380px;
        max-height: min(46dvh, 340px);
        margin-left: auto;
        box-sizing: border-box;
        gap: 3px;
        padding: 6px;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        border-radius: 12px;
    }
    .composer-tools-menu .composer-tool-btn {
        min-height: 34px;
        gap: 6px;
        padding: 5px 6px;
        font-size: .74rem;
        line-height: 1.2;
    }
    .composer-tools-menu .composer-tool-btn > i {
        width: 17px;
        flex-basis: 17px;
        font-size: .9rem;
    }
    .composer-tools-menu .chat-call-push-settings {
        padding: 4px 6px;
    }
    .composer-tools-menu #chat-call-push-toggle {
        min-width: 0;
        font-size: .74rem;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }
    .composer-tools-menu #chat-call-push-status {
        font-size: .68rem;
        overflow-wrap: anywhere;
    }
}
@media (max-width: 420px) {
    .composer-tools-menu {
        left: 8px;
        right: 8px;
        bottom: calc(64px + env(safe-area-inset-bottom));
        max-width: none;
        max-height: min(44dvh, 310px);
        gap: 2px;
        padding: 5px;
    }
    .composer-tools-menu .composer-tool-btn {
        min-height: 32px;
        font-size: .7rem;
        padding: 4px 5px;
    }
}

</style>


<div class="telegram-chat-layout">
    <aside class="telegram-chat-sidebar">
        <div class="telegram-sidebar-heading">
            <strong>DAODES <span>MESSENGER</span></strong>
            <a href="{{ route('chats.index') }}" aria-label="{{ __('chats.to_chats') }}">⌂</a>
        </div>
        <nav class="telegram-sidebar-actions" aria-label="Навигация мессенджера">
            <a href="{{ route('chats.index') }}"><i class="fa fa-comments" aria-hidden="true"></i><span>Чаты</span></a>
            <a href="{{ route('contacts.index') }}"><i class="fa fa-address-book" aria-hidden="true"></i><span>Контакты</span></a>
            <a href="{{ route('chats.create') }}"><i class="fa fa-plus" aria-hidden="true"></i><span>Новый чат</span></a>
            <a href="{{ route('chats.notifications') }}"><i class="fa fa-bell" aria-hidden="true"></i><span>Уведомления</span></a>
        </nav>
        <section class="telegram-sidebar-stories" id="chatStoriesBar"
                 data-user-id="{{ auth()->id() }}"
    data-peer-user-id="{{ $otherUser?->id ?? '' }}"
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
            <div class="chat-stories-list" id="chatStoriesList"></div>
            <input type="file" id="chatStoryFileInput" accept="image/*,video/*" hidden>
        </section>
        @if ($chat->type === 'channel')
            <section class="telegram-sidebar-stories" id="channelStoriesBar"
                     data-chat-id="{{ $chat->id }}"
                     data-label-story-views="{{ __('chats.story_views') }}"
                     data-label-story-delete="{{ __('chats.story_delete') }}"
                     data-label-stories-empty="{{ __('chats.stories_empty') }}"
                     data-label-story-upload-error="{{ __('chats.story_upload_error') }}">
                @if ($currentRole === 'owner' || ($currentRole === 'admin' && $chat->allow_admin_stories) || ($currentRole === 'moderator' && $chat->allow_moderator_stories))
                    <button type="button" class="chat-story-add" id="addChannelStoryBtn">
                        <span class="chat-story-add-avatar">＋</span>
                        <span>{{ __('chats.channel_stories') }}</span>
                    </button>
                    <input type="file" id="channelStoryFileInput" accept="image/*,video/*" hidden>
                @endif
                <div class="chat-stories-list" id="channelStoriesList"></div>
            </section>
        @endif
        <input
            type="search"
            id="telegramChatSearch"
            class="telegram-chat-search"
            placeholder="{{ __('chats.search_chats') }}"
            autocomplete="off"
        >
        <nav class="telegram-chat-list" id="telegramChatList" aria-label="{{ __('chats.your_chats') }}">
            @foreach ($sidebarChats as $sidebarChat)
                @php
                    $sidebarOther = $sidebarChat->type === 'personal'
                        ? $sidebarChat->users->first(fn($participant) => $participant->id !== auth()->id())
                        : null;
                    $sidebarName = $sidebarChat->getChatNameForUser(auth()->id());
                @endphp
                <a
                    href="{{ route('chats.show', $sidebarChat->id) }}"
                    class="telegram-chat-row {{ $sidebarChat->id === $chat->id ? 'active' : '' }}"
                    data-search="{{ mb_strtolower($sidebarName) }}"
                >
                    @if ($sidebarChat->type === 'saved')
                        <span class="telegram-chat-avatar saved"><i class="fa fa-bookmark"></i></span>
                    @elseif ($sidebarChat->type === 'channel' && $sidebarChat->photo_cid)
                        <img class="telegram-chat-avatar" src="{{ '/ipfs/' . $sidebarChat->photo_cid }}" alt="">
                    @elseif ($sidebarOther)
                        <img
                            class="telegram-chat-avatar"
                            src="{{ $sidebarOther->profile?->avatar_url ?: '/img/main/default-avatar.png' }}"
                            alt=""
                        >
                    @else
                        <span class="telegram-chat-avatar group"><i class="fa fa-users"></i></span>
                    @endif
                    <span class="telegram-chat-row-body">
                        <strong>{{ $sidebarName }}</strong>
                        <small>
                            @if ($sidebarOther)
                                {{ $sidebarOther->isOnline() ? __('chats.online') : $sidebarOther->lastSeenHuman() }}
                            @elseif ($sidebarChat->type === 'channel')
                                {{ __('chats.channel') }}
                            @elseif ($sidebarChat->type === 'saved')
                                {{ __('chats.saved_messages') }}
                            @else
                                {{ __('chats.group_chat') }}
                            @endif
                        </small>
                    </span>
                    @if (($sidebarUnread[$sidebarChat->id] ?? 0) > 0)
                        <span class="telegram-chat-unread">{{ $sidebarUnread[$sidebarChat->id] }}</span>
                    @endif
                </a>
                @if ($sidebarChat->id === $chat->id)
                    <details class="telegram-chat-topics" open>
                        <summary>Темы чата</summary>
                        <div class="chat-topics-bar chat-topics-sidebar" id="chatTopicsBar">
                            <button type="button" class="chat-topic-tab {{ $initialTopicId === null ? 'active' : '' }}" data-topic-id="">
                                {{ __('chats.all_messages') }}
                            </button>
                            @foreach ($topics as $topic)
                                <button
                                    type="button"
                                    class="chat-topic-tab {{ $initialTopicId === (int) $topic->id ? 'active' : '' }}"
                                    data-topic-id="{{ $topic->id }}"
                                    title="{{ $topic->description }}"
                                >{{ $topic->name }}</button>
                            @endforeach
                            @if (in_array($currentRole, ['owner', 'admin', 'moderator'], true) && $chat->type !== 'personal')
                                <button type="button" class="chat-topic-create" id="createChatTopic">
                                    + {{ __('chats.new_topic') }}
                                </button>
                            @endif
                        </div>
                    </details>
                @elseif ($sidebarChat->topics->isNotEmpty())
                    <details class="telegram-chat-topics">
                        <summary>Темы чата ({{ $sidebarChat->topics->count() }})</summary>
                        <div class="telegram-chat-topic-links">
                            <a href="{{ route('chats.show', $sidebarChat->id) }}">Все сообщения</a>
                            @foreach ($sidebarChat->topics as $sidebarTopic)
                                <a href="{{ route('chats.show', $sidebarChat->id) }}?topic_id={{ $sidebarTopic->id }}">{{ $sidebarTopic->name }}</a>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
        </nav>
    </aside>
    <main class="telegram-chat-main">
<div class="chat-container">

    {{-- =====================================================
         ЗАГОЛОВОК ЧАТА
         ===================================================== --}}

    <a class="telegram-mobile-back" href="{{ route('chats.index') }}">
        <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ __('chats.to_chats') }}
    </a>
    @if ($topics->isNotEmpty())
        <details class="chat-topics-mobile">
            <summary>Темы чата</summary>
            <a href="{{ route('chats.show', $chat->id) }}">Все сообщения</a>
            @foreach ($topics as $topic)
                <a href="{{ route('chats.show', $chat->id) }}?topic_id={{ $topic->id }}">{{ $topic->name }}</a>
            @endforeach
        </details>
    @endif

    <div class="chat-header">

        @if ($chat->type === 'personal' && $otherUser)

            <button
                type="button"
                class="chat-person-heading chat-profile-trigger"
                id="openChatPeerProfile"
                aria-haspopup="dialog"
                aria-controls="chatPeerProfileModal"
                title="{{ __('user_profile.open_profile_card') }}"
            >
                <img
                    class="chat-header-avatar"
                    src="{{ $otherUser->profile?->avatar_url ?: '/img/main/default-avatar.png' }}"
                    alt=""
                >
                <span class="chat-person-heading-info">
                    <span class="chat-title">{{ $otherUser->name }}</span>
                    <span class="chat-status">
                        @if ($otherUser->isOnline())
                            <span class="status-indicator online"></span>{{ __('chats.online') }}
                        @else
                            <span class="status-indicator offline"></span>{{ $otherUser->lastSeenHuman() }}
                        @endif
                    </span>
                    @if ($otherUser->profile?->status_text && (!$otherUser->profile->status_expires_at || $otherUser->profile->status_expires_at->isFuture()))
                        <span class="chat-custom-status">
                            {{ $otherUser->profile->status_emoji }} {{ $otherUser->profile->status_text }}
                        </span>
                    @endif
                    <span class="chat-profile-hint"><i class="fa fa-address-card-o" aria-hidden="true"></i> {{ __('user_profile.business_card') }}</span>
                </span>
            </button>

        @elseif ($chat->type === 'saved')

            <div class="chat-person-heading">
                <span class="chat-header-avatar chat-header-saved-avatar"><i class="fa fa-bookmark"></i></span>
                <div class="chat-person-heading-info">
                    <h2 class="chat-title">{{ $chat->name }}</h2>
                    <p class="chat-status">{{ __('chats.saved_messages_description') }}</p>
                </div>
            </div>

        @elseif ($chat->type === 'channel')

            <div class="chat-person-heading">
                <img class="chat-header-avatar" id="channelHeaderPhoto"
                     src="{{ $chat->photo_cid ? '/ipfs/' . $chat->photo_cid : '/img/main/default-avatar.png' }}"
                     alt="">
                <div class="chat-person-heading-info">
                    <h2 class="chat-title">{{ $chat->name }}</h2>
                    <p class="chat-status">{{ __('chats.online_participants', ['online' => $chat->onlineParticipantsCount(), 'total' => $chat->totalParticipantsCount()]) }}</p>
                </div>
                <button type="button" class="chat-channel-info-trigger" id="openChatChannelInfo" aria-label="Информация о канале" title="Информация о канале">
                    <i class="fa fa-info-circle" aria-hidden="true"></i>
                </button>
            </div>
            @if ($currentRole === 'owner' || ($currentRole === 'admin' && $chat->allow_admin_photo) || ($currentRole === 'moderator' && $chat->allow_moderator_photo))
                <label class="chat-topic-create" for="channelPhotoInput">{{ __('chats.channel_photo_change') }}</label>
                <input type="file" id="channelPhotoInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
            @endif


        @else

            <h2 class="chat-title">
                {{ $chat->name }}
            </h2>

            <p class="chat-status">

                {{ __('chats.online_participants', [
                    'online' => $chat->onlineParticipantsCount(),
                    'total' => $chat->totalParticipantsCount(),
                ]) }}

            </p>

        @endif

        <div class="chat-mute-controls">
            <label for="chatMuteDuration">{{ __('chats.mute_notifications') }}</label>
            <select id="chatMuteDuration">
                <option value="">{{ __('chats.mute_off') }}</option>
                <option value="60">{{ __('chats.mute_1_hour') }}</option>
                <option value="480">{{ __('chats.mute_8_hours') }}</option>
                <option value="1440">{{ __('chats.mute_1_day') }}</option>
                <option value="10080">{{ __('chats.mute_7_days') }}</option>
                <option value="43200">{{ __('chats.mute_30_days') }}</option>
            </select>
            <button type="button" id="saveChatMute">{{ __('chats.save') }}</button>
            <span class="chat-mute-status" id="chatMuteStatus" aria-live="polite">
                @if ($mutedUntil && \Illuminate\Support\Carbon::parse($mutedUntil)->isFuture())
                    {{ __('chats.muted_until', ['date' => \Illuminate\Support\Carbon::parse($mutedUntil)->format('d.m.Y H:i')]) }}
                @else
                    {{ __('chats.notifications_enabled') }}
                @endif
            </span>
        </div>

        <div class="chat-message-search">
            <button type="button" id="toggleMessageSearch" title="{{ __('chats.search_messages') }}">
                <i class="fa fa-search" aria-hidden="true"></i>
            </button>
            <div id="messageSearchPanel" hidden>
                <input
                    type="search"
                    id="chatMessageSearchInput"
                    placeholder="{{ __('chats.search_loaded_messages') }}"
                    autocomplete="off"
                >
                <span id="chatMessageSearchCount" aria-live="polite"></span>
                <button type="button" id="clearMessageSearch" aria-label="{{ __('chats.clear_search') }}">×</button>
            </div>
        </div>

    </div>


    @if ($chat->type === 'channel')
        <div class="chat-channel-info-modal" id="chatChannelInfoModal" hidden>
            <section class="chat-channel-info-card" role="dialog" aria-modal="true" aria-labelledby="chatChannelInfoTitle">
                <button type="button" class="chat-peer-profile-close" id="closeChatChannelInfo" aria-label="Закрыть">×</button>
                <div class="chat-channel-info-heading">
                    <img src="{{ $chat->photo_cid ? '/ipfs/' . $chat->photo_cid : '/img/main/default-avatar.png' }}" alt="">
                    <div><h3 id="chatChannelInfoTitle">{{ $chat->name }}</h3><p>{{ __('chats.channel') }}</p></div>
                </div>
                <div class="chat-channel-info-stats">
                    <span><strong>{{ $chat->totalParticipantsCount() }}</strong> участников</span>
                    <span><strong>{{ $chat->onlineParticipantsCount() }}</strong> в сети</span>
                    <span><strong>{{ __('chats.role_' . $currentRole) }}</strong> — ваша роль</span>
                </div>
                <h4 class="chat-channel-info-subtitle">{{ __('chats.participants') }}</h4>
                <div class="chat-channel-info-members">
                    @foreach ($chat->users as $channelMember)
                        <div><span>{{ $channelMember->name }}</span><small>{{ __('chats.role_' . ($channelMember->pivot->role ?? 'member')) }}</small></div>
                    @endforeach
                </div>
                @if ($currentRole === 'owner')
                    <details class="chat-channel-settings" open>
                        <summary>{{ __('chats.channel_settings_permissions') }}</summary>
                        <form id="channelSettingsForm" data-url="{{ route('chats.channel.settings', $chat->id) }}" data-label-saved="{{ __('chats.channel_permissions_saved') }}" data-label-save-error="{{ __('chats.channel_permissions_save_error') }}">
                            <label><input type="checkbox" name="allow_admin_photo" value="1" @checked($chat->allow_admin_photo)> {{ __('chats.channel_admin_photo_permission') }}</label>
                            <label><input type="checkbox" name="allow_moderator_photo" value="1" @checked($chat->allow_moderator_photo)> {{ __('chats.channel_moderator_photo_permission') }}</label>
                            <label><input type="checkbox" name="allow_admin_stories" value="1" @checked($chat->allow_admin_stories)> {{ __('chats.channel_admin_stories_permission') }}</label>
                            <label><input type="checkbox" name="allow_moderator_stories" value="1" @checked($chat->allow_moderator_stories)> {{ __('chats.channel_moderator_stories_permission') }}</label>
                            <button type="submit">Сохранить права</button>
                            <span id="channelSettingsStatus" aria-live="polite"></span>
                        </form>
                    </details>
                @endif
            </section>
        </div>
    @endif

    @if ($chat->type === 'personal' && $otherUser)
        <div class="chat-peer-profile-modal" id="chatPeerProfileModal" hidden>
            <section class="chat-peer-profile-card" role="dialog" aria-modal="true" aria-labelledby="chatPeerProfileTitle">
                <button type="button" class="chat-peer-profile-close" id="closeChatPeerProfile" aria-label="Закрыть">×</button>
                <div class="chat-peer-profile-heading">
                    <img src="{{ $otherUser->profile?->avatar_url ?: '/img/main/default-avatar.png' }}" alt="">
                    <div>
                        <h3 id="chatPeerProfileTitle">{{ $otherUser->name }}</h3>
                        @if ($otherUser->profile?->card_position)
                            <p>{{ $otherUser->profile->card_position }}</p>
                        @endif
                    </div>
                </div>

                <div class="chat-peer-profile-details">
                    @if ($otherUser->profile?->card_company)
                        <p><i class="fa fa-briefcase" aria-hidden="true"></i><span>{{ $otherUser->profile->card_company }}</span></p>
                    @endif
                    @if ($otherUser->profile?->card_location)
                        <p><i class="fa fa-map-marker" aria-hidden="true"></i><span>{{ $otherUser->profile->card_location }}</span></p>
                    @endif
                    @if ($otherUser->profile?->card_about)
                        <p class="chat-peer-profile-about">{{ $otherUser->profile->card_about }}</p>
                    @endif
                    @if ($otherUser->profile?->card_website)
                        <p><i class="fa fa-globe" aria-hidden="true"></i><a href="{{ $otherUser->profile->card_website }}" target="_blank" rel="noopener noreferrer">{{ $otherUser->profile->card_website }}</a></p>
                    @endif
                    @if ($otherUser->profile?->nickname)
                        <p><i class="fa fa-telegram" aria-hidden="true"></i><a href="https://t.me/{{ $otherUser->profile->nickname }}" target="_blank" rel="noopener noreferrer">{{ '@' . $otherUser->profile->nickname }}</a></p>
                    @endif
                    @if (!$otherUser->profile || (!$otherUser->profile->card_position && !$otherUser->profile->card_company && !$otherUser->profile->card_location && !$otherUser->profile->card_about && !$otherUser->profile->card_website && !$otherUser->profile->nickname))
                        <p class="chat-peer-profile-empty">{{ __('user_profile.business_card_empty') }}</p>
                    @endif
                </div>

                @if ($otherUser->profile)
                    <a class="chat-peer-profile-link" href="{{ route('user_profile.show', $otherUser->id) }}">{{ __('user_profile.open_full_profile') }}</a>
                @endif
            </section>
        </div>
    @endif

    <div class="chat-members-panel">
        <details>
            <summary>
                {{ __('chats.participants') }} ({{ $chat->users->count() }})
            </summary>
            <div class="chat-members-list" id="chatMembersList">
                @foreach ($chat->users as $member)
                    <div class="chat-member" data-user-id="{{ $member->id }}">
                        <span class="chat-member-name">{{ $member->name }}</span>
                        <span class="chat-member-role">{{ __('chats.role_' . ($member->pivot->role ?? 'member')) }}</span>

                        @if (in_array($currentRole, ['owner', 'admin'], true) && !in_array($chat->type, ['personal', 'saved'], true) && $member->id !== auth()->id() && ($member->pivot->role ?? 'member') !== 'owner' && ($currentRole === 'owner' || ($member->pivot->role ?? 'member') !== 'admin'))
                            @if ($currentRole === 'owner')
                                <select class="chat-member-role-select" aria-label="Role">
                                    <option value="member" @selected(($member->pivot->role ?? 'member') === 'member')>{{ __('chats.role_member') }}</option>
                                    <option value="moderator" @selected(($member->pivot->role ?? 'member') === 'moderator')>{{ __('chats.role_moderator') }}</option>
                                    <option value="admin" @selected(($member->pivot->role ?? 'member') === 'admin')>{{ __('chats.role_admin') }}</option>
                                </select>
                            @endif
                            <button type="button" class="chat-member-remove" data-user-id="{{ $member->id }}">
                                {{ __('chats.remove_participant') }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </details>

        @if (in_array($currentRole, ['owner', 'admin'], true) && !in_array($chat->type, ['personal', 'saved'], true))
            <button type="button" class="chat-topic-create" id="addChatParticipant">
                + {{ __('chats.add_participant') }}
            </button>
            <div class="chat-participant-modal" id="chatParticipantModal" hidden>
                <label for="chatParticipantSelect">{{ __('chats.select_participant') }}</label>
                <select id="chatParticipantSelect"></select>
                <div class="chat-participant-modal-actions">
                    <button type="button" id="confirmAddChatParticipant">{{ __('chats.add_participant') }}</button>
                    <button type="button" id="cancelAddChatParticipant">{{ __('chats.cancel') }}</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Темы чата перенесены во вложенное меню соответствующего чата в боковой панели. --}}

    <div id="chatPinnedMessages" class="chat-pinned-messages" hidden aria-live="polite"></div>

    {{-- =====================================================
         СООБЩЕНИЯ
         ===================================================== --}}

    <div
        id="chat-messages"
        class="chat-messages"
    >

        @foreach ($chat->messages as $message)

            <div
                class="message {{ $message->sender_id === auth()->id() ? 'sent' : 'received' }}"
                data-id="{{ $message->id }}"
                data-ipfs-cid="{{ $message->ipfs_cid }}"
                data-message-type="{{ $message->message_type ?? 'text' }}"
                data-attachment-id="{{ $message->attachments->first()?->id ?? '' }}"
            >

                @if ($message->reply_to_message_id)

                    <div
                        class="reply-preview"
                        data-reply-id="{{ $message->reply_to_message_id }}"
                    >

                        <div class="reply-indicator">
                            <i
                                class="fa fa-reply"
                                aria-hidden="true"
                            ></i>
                        </div>

                        <div class="reply-content">

                            {{ __('chats.download') }}

                            <i
                                class="fa fa-spinner"
                                aria-hidden="true"
                            ></i>

                        </div>

                    </div>

                @endif


                <div
                    class="{{ $message->sender_id === auth()->id() ? 'my-card-body' : 'card-body' }}"
                >

                    <p class="card-title">

                        {{ $message->sender_id === auth()->id()
                            ? 'Вы'
                            : $message->sender->name
                        }}

                        <small>
                            {{ $message->created_at->format('H:i, d M') }}
                        </small>

                        @if ($message->edited_at)

                            <small class="message-status edited">
                                ✏️
                            </small>

                        @endif

                    </p>


                    <p class="card-text">

                        {{ __('chats.download') }}

                        <i
                            class="fa fa-spinner"
                            aria-hidden="true"
                        ></i>

                    </p>

                </div>

                <div class="message-reactions" data-message-id="{{ $message->id }}">
                    <button type="button" class="add-reaction" aria-label="Add reaction">＋</button>
                    <div class="reaction-picker" hidden>
                        @foreach (['👍', '❤️', '😂', '🔥', '👏', '😮', '😢', '🎉'] as $reaction)
                            <button type="button" class="reaction-choice" data-reaction="{{ $reaction }}">{{ $reaction }}</button>
                        @endforeach
                    </div>
                </div>

            </div>

        @endforeach

    </div>
    <button type="button" id="chatScrollToBottom" class="chat-scroll-bottom" aria-label="Прокрутить вниз" title="К последним сообщениям" hidden>
        <i class="fa fa-arrow-down" aria-hidden="true"></i>
    </button>


    {{-- =====================================================
         ПРЕВЬЮ ОТВЕТА
         ===================================================== --}}

    <div
        id="replyPreview"
        class="reply-preview-form"
        style="display: none;"
    >

        <div class="reply-info">

            <span class="reply-icon">

                <i
                    class="fa fa-reply"
                    aria-hidden="true"
                ></i>

            </span>

            <span class="reply-to-name"></span>

            <span class="reply-to-text"></span>

        </div>

        <button type="button" id="editReplyQuote" class="edit-reply-quote" title="Вставить редактируемую копию цитаты">
            <i class="fa fa-quote-right" aria-hidden="true"></i>
        </button>

        <button
            type="button"
            id="cancelReply"
            class="cancel-reply"
        >
            ✕
        </button>

    </div>


    <div class="chat-poll-modal" id="chatPollModal" hidden>
        <form id="chatPollForm" class="chat-poll-form">
            <h3>{{ __('chats.poll_create') }}</h3>
            <label for="chatPollQuestion">{{ __('chats.poll_question') }}</label>
            <input id="chatPollQuestion" name="question" type="text" maxlength="500" required>
            <label for="chatPollOptions">{{ __('chats.poll_options') }}</label>
            <textarea id="chatPollOptions" name="options" rows="5" maxlength="2000" required placeholder="{{ __('chats.poll_options_hint') }}"></textarea>
            <label class="chat-poll-checkbox"><input type="checkbox" name="is_anonymous" checked> {{ __('chats.poll_anonymous') }}</label>
            <label class="chat-poll-checkbox"><input type="checkbox" name="allows_multiple"> {{ __('chats.poll_multiple') }}</label>
            <div class="chat-poll-form-actions">
                <button type="button" id="cancelPollBtn">{{ __('chats.cancel') }}</button>
                <button type="submit">{{ __('chats.poll_create') }}</button>
            </div>
        </form>
    </div>

    @if ($chat->type === 'personal' && $otherUser)
        <div class="chat-call-modal" id="chatCallModal" hidden>
            <section class="chat-call-panel" role="dialog" aria-modal="true" aria-labelledby="chatCallTitle">
                <h3 id="chatCallTitle">Звонок</h3>
                <p class="chat-call-status" id="chatCallStatus" aria-live="polite">Ожидание…</p>
                <div class="chat-call-videos">
                    <video id="chatCallRemoteVideo" autoplay playsinline muted></video>
                    <video id="chatCallLocalVideo" autoplay muted playsinline></video>
                    <audio id="chatCallRemoteAudio" autoplay></audio>
                </div>
                <div class="chat-call-actions">
                    <button type="button" id="toggleChatCallMic" hidden>Выключить микрофон</button>
                    <button type="button" id="toggleChatCallCamera" hidden>Выключить камеру</button>
                    <button type="button" id="shareChatCallScreen" hidden>Поделиться экраном</button>
                    <button type="button" id="acceptChatCall" hidden>Принять</button>
                    <button type="button" id="rejectChatCall" class="danger" hidden>Отклонить</button>
                    <button type="button" id="endChatCall" class="danger" hidden>Завершить</button>
                </div>
            </section>
        </div>
    @endif

    @if ($chat->type !== 'saved')
        <div class="scheduled-message-modal" id="chatMeetingsModal" hidden>
            <section class="scheduled-message-panel" role="dialog" aria-modal="true" aria-labelledby="chatMeetingsTitle">
                <h3 id="chatMeetingsTitle">Встречи и запланированные созвоны</h3>
                <form id="chatMeetingForm">
                    <label for="chatMeetingName">Название</label>
                    <input id="chatMeetingName" name="title" type="text" maxlength="500" required>
                    <label for="chatMeetingDescription">Описание (необязательно)</label>
                    <input id="chatMeetingDescription" name="description" type="text" maxlength="2000">
                    <label for="chatMeetingStartsAt">Дата и время</label>
                    <input id="chatMeetingStartsAt" name="starts_at" type="datetime-local" required>
                    <label for="chatMeetingDuration">Продолжительность</label>
                    <select id="chatMeetingDuration" name="duration_minutes">
                        <option value="15">15 минут</option>
                        <option value="30" selected>30 минут</option>
                        <option value="45">45 минут</option>
                        <option value="60">1 час</option>
                        <option value="90">1 час 30 минут</option>
                        <option value="120">2 часа</option>
                    </select>
                    <label>Пригласить участников</label>
                    <div class="chat-meeting-invitees">
                        @foreach ($chat->users as $meetingParticipant)
                            @if ((int) $meetingParticipant->id !== (int) auth()->id())
                                <label>
                                    <input type="checkbox" name="participant_ids" value="{{ $meetingParticipant->id }}" checked>
                                    {{ $meetingParticipant->name }}
                                </label>
                            @endif
                        @endforeach
                    </div>
                    <div class="scheduled-message-actions">
                        <button type="button" id="closeChatMeetings">Закрыть</button>
                        <button type="submit">Создать встречу</button>
                    </div>
                </form>
                <div id="chatMeetingsList" aria-live="polite"></div>
            </section>
        </div>
    @endif

    @if ($chat->type === 'group')
        <div class="group-call-modal" id="groupCallModal" hidden>
            <section class="group-call-panel" role="dialog" aria-modal="true" aria-labelledby="groupCallTitle">
                <h3 id="groupCallTitle">Групповой звонок</h3>
                <p class="group-call-status" id="groupCallStatus" aria-live="polite">Ожидание подключения…</p>
                <div class="group-call-participants" id="groupCallParticipants"></div>
                <div class="group-call-actions">
                    <button type="button" id="groupCallMic">Выключить микрофон</button>
                    <button type="button" id="groupCallCamera">Включить камеру</button>
                    <button type="button" id="groupCallScreen">Поделиться экраном</button>
                    <button type="button" id="groupCallLeave" class="danger">Покинуть звонок</button>
                    <button type="button" id="groupCallClose">Закрыть</button>
                </div>
            </section>
        </div>
    @endif

    <div class="scheduled-message-modal" id="scheduledMessagesModal" hidden>
        <section class="scheduled-message-panel" role="dialog" aria-modal="true" aria-labelledby="scheduledMessagesTitle">
            <h3 id="scheduledMessagesTitle">Отложенные сообщения</h3>
            <form id="scheduledMessageForm">
                <label for="scheduledMessageDate">Дата и время отправки</label>
                <input id="scheduledMessageDate" type="datetime-local" required>
                <div class="scheduled-message-actions">
                    <button type="button" id="closeScheduledMessages">Закрыть</button>
                    <button type="submit">Запланировать</button>
                </div>
            </form>
            <div id="scheduledMessagesList" aria-live="polite"></div>
        </section>
    </div>

    {{-- =====================================================
         ФОРМА ОТПРАВКИ
         ===================================================== --}}

    <form
        id="messageForm"
        onsubmit="return false;"
        action="{{ route('messages.send', $chat->id) }}"
    >

        @csrf

        <div class="chat-format-toolbar" id="chatFormatToolbar" role="toolbar" aria-label="Форматирование сообщения">
            <button type="button" data-format="bold" title="Жирный"><strong>B</strong></button>
            <button type="button" data-format="italic" title="Курсив"><em>I</em></button>
            <button type="button" data-format="quote" title="Цитата">❝</button>
            <button type="button" data-format="strike" title="Зачёркнутый"><s>S</s></button>
            <button type="button" data-format="code" title="Код">&lt;/&gt;</button>
            <button type="button" data-format="normal" title="Обычный текст">T</button>
        </div>

        <div class="input-group">

            <div class="input-wrapper">

                <textarea
                    id="messageInput"
                    name="message"
                    placeholder="{{ $chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true) ? __('chats.channel_read_only') : __('chats.type_message') }}"
                    rows="1"
                    required
                    @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                ></textarea>

                <button
                    type="button"
                    class="chat-emoji-toggle"
                    id="chatEmojiToggle"
                    aria-label="Открыть эмодзи"
                    title="Эмодзи"
                    aria-expanded="false"
                    aria-controls="chatEmojiPanel"
                    @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                >🙂</button>
                <div class="chat-emoji-panel" id="chatEmojiPanel" hidden>
                    <emoji-picker
                        id="chatEmojiPicker"
                        locale="ru"
                        data-source="https://cdn.jsdelivr.net/npm/emoji-picker-element-data@^1/ru/emojibase/data.json"
                    ></emoji-picker>
                </div>
                <div class="chat-mention-suggestions" id="chatMentionSuggestions" hidden></div>

            </div>


            <div class="composer-tools">
                <button
                    type="button"
                    id="toggleComposerTools"
                    class="composer-tools-toggle"
                    aria-label="Дополнительные действия"
                    title="Дополнительные действия"
                    aria-expanded="false"
                    aria-controls="composerToolsMenu"
                    @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                >
                    <i class="fa fa-plus" aria-hidden="true"></i>
                </button>

                <div class="composer-tools-menu" id="composerToolsMenu" hidden>
                    <button
                        type="button"
                        id="openScheduledMessages"
                        class="composer-tool-btn"
                        aria-label="Отложенные сообщения"
                        title="Отложенные сообщения"
                        @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                    >
                        <i class="fa fa-clock-o" aria-hidden="true"></i>
                        <span>Отложенные сообщения</span>
                    </button>

                    @if ($chat->type === 'personal' && $otherUser)
                        <button type="button" id="startAudioCall" class="composer-tool-btn">
                            <i class="fa fa-phone" aria-hidden="true"></i><span>Аудиозвонок</span>
                        </button>
                        <button type="button" id="startVideoCall" class="composer-tool-btn">
                            <i class="fa fa-video-camera" aria-hidden="true"></i><span>Видеозвонок</span>
                        </button>
                        @include('components.chat-call-push-subscription')
                    @endif

                    @if ($chat->type !== 'saved')
                        <button type="button" id="openChatMeetings" class="composer-tool-btn">
                            <i class="fa fa-calendar" aria-hidden="true"></i><span>Запланировать созвон</span>
                        </button>
                    @endif

                    <button
                        type="button"
                        id="createPollBtn"
                        class="composer-tool-btn"
                        aria-label="{{ __('chats.poll_create') }}"
                        title="{{ __('chats.poll_create') }}"
                        @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                    >
                        <i class="fa fa-bar-chart" aria-hidden="true"></i>
                        <span>{{ __('chats.poll_create') }}</span>
                    </button>

                    <button
                        type="button"
                        id="attachChatFileBtn"
                        class="composer-tool-btn"
                        aria-label="{{ __('chats.attach_file') }}"
                        title="{{ __('chats.attach_file') }}"
                        @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                    >
                        <i class="fa fa-paperclip" aria-hidden="true"></i>
                        <span>{{ __('chats.attach_file') }}</span>
                    </button>

                    <button
                        type="button"
                        id="recordVideoNoteBtn"
                        class="composer-tool-btn"
                        aria-label="{{ __('chats.video_note_record') }}"
                        title="{{ __('chats.video_note_record') }}"
                        @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                    >
                        <i class="fa fa-video-camera" aria-hidden="true"></i>
                        <span>{{ __('chats.video_note_record') }}</span>
                    </button>

                    <button
                        type="button"
                        id="recordVoiceBtn"
                        class="composer-tool-btn"
                        aria-label="{{ __('chats.voice_record') }}"
                        title="{{ __('chats.voice_record') }}"
                        @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                    >
                        <i class="fa fa-microphone" aria-hidden="true"></i>
                        <span>{{ __('chats.voice_record') }}</span>
                    </button>
                </div>

                <input
                    type="file"
                    id="chatAttachmentInput"
                    hidden
                >
            </div>

            <button
                type="button"
                id="sendBtn"
                class="send-btn"
                aria-label="Отправить сообщение"
                title="Отправить сообщение"
                @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
            >

                <i
                    class="fa fa-paper-plane"
                    aria-hidden="true"
                ></i>

            </button>

        </div>
        <div id="chatComposerStatus" class="chat-composer-status" role="status" aria-live="polite" hidden></div>

    </form>


    {{-- Навигация вынесена в боковую панель мессенджера. --}}

    {{-- =====================================================
         ПЕРЕВОДЫ
         ===================================================== --}}

    <div
        id="translations"
        data-reply="{{ __('chats.reply') }}"
        data-edit="{{ __('chats.edit') }}"
        data-delete="{{ __('chats.delete') }}"
        data-copy="{{ __('chat.copy') }}"
        style="display:none;"
    >
    </div>

</div>
    </main>
</div>


{{-- =========================================================
     ЗВУК УВЕДОМЛЕНИЯ
     ========================================================= --}}

<audio
    id="notificationSound"
    preload="auto"
>

    <source
        src="/sounds/notification.mp3"
        type="audio/mpeg"
    >

</audio>


{{-- =========================================================
     КОНФИГУРАЦИЯ ЧАТА
     ========================================================= --}}

<div
    id="chat-config"
    data-chat-id="{{ $chat->id }}"
    data-chat-type="{{ $chat->type }}"
    data-topic-id="{{ $initialTopicId ?? '' }}"
    data-current-role="{{ $currentRole }}"
    data-user-id="{{ auth()->id() }}"
    data-user-name="{{ auth()->user()->name }}"
    data-first-unread="{{ $firstUnreadMessageId ?? '' }}"
    data-muted-until="{{ $mutedUntil ?? '' }}"
    data-translate-muted-until="{{ __('chats.muted_until', ['date' => ':date']) }}"
    data-translate-notifications-enabled="{{ __('chats.notifications_enabled') }}"
    data-send-url="{{ route('messages.send', $chat->id) }}"
    data-csrf="{{ csrf_token() }}"
    data-translate-edit="{{ __('chats.edit') }}"
    data-translate-delete="{{ __('chats.delete') }}"
    data-translate-reply="{{ __('chats.reply') }}"
    data-translate-send="{{ __('chats.send') }}"
    data-translate-save="{{ __('chats.save') }}"
    data-translate-copy="{{ __('chats.copy') }}"
    data-translate-download="{{ __('chats.download') }}"
    data-translate-message-deleted="{{ __('chats.message_deleted') }}"
    data-translate-delete-confirm="{{ __('chats.delete_confirm') }}"
    data-translate-send-error="{{ __('chats.send_error') }}"
    data-translate-you="{{ __('chats.you') }}"
    data-translate-topic-name-prompt="{{ __('chats.topic_name_prompt') }}"
    data-translate-topic-create-error="{{ __('chats.topic_create_error') }}"
    data-translate-voice-play="{{ __('chats.voice_play') }}"
    data-translate-voice-loading="{{ __('chats.voice_loading') }}"
    data-translate-voice-error="{{ __('chats.voice_error') }}"
    data-translate-voice-transcribe="{{ __('chats.voice_transcribe') }}"
    data-translate-voice-transcribing="{{ __('chats.voice_transcribing') }}"
    data-translate-voice-transcript-hide="{{ __('chats.voice_transcript_hide') }}"
    data-translate-voice-transcript-error="{{ __('chats.voice_transcript_error') }}"
    data-translate-voice-transcript-consent="{{ __('chats.voice_transcript_consent') }}"
    data-translate-voice-record="{{ __('chats.voice_record') }}"
    data-translate-voice-stop="{{ __('chats.voice_stop') }}"
    data-translate-attach-file="{{ __('chats.attach_file') }}"
    data-translate-media-loading="{{ __('chats.media_loading') }}"
    data-translate-media-error="{{ __('chats.media_error') }}"
    data-translate-media-download="{{ __('chats.media_download') }}"
    data-translate-open-fullscreen="{{ __('chats.open_fullscreen') }}"
    data-translate-search-messages="{{ __('chats.search_messages') }}"
    data-translate-role-member="{{ __('chats.role_member') }}"
    data-translate-role-moderator="{{ __('chats.role_moderator') }}"
    data-translate-role-admin="{{ __('chats.role_admin') }}"
    data-translate-pin="{{ __('chats.pin') }}"
    data-translate-unpin="{{ __('chats.unpin') }}"
    data-translate-pinned-messages="{{ __('chats.pinned_messages') }}"
    data-translate-poll-create="{{ __('chats.poll_create') }}"
    data-translate-poll-question="{{ __('chats.poll_question') }}"
    data-translate-poll-options="{{ __('chats.poll_options') }}"
    data-translate-poll-options-hint="{{ __('chats.poll_options_hint') }}"
    data-translate-poll-anonymous="{{ __('chats.poll_anonymous') }}"
    data-translate-poll-open="{{ __('chats.poll_open') }}"
    data-translate-poll-closed="{{ __('chats.poll_closed') }}"
    data-translate-poll-multiple="{{ __('chats.poll_multiple') }}"
    data-translate-poll-vote="{{ __('chats.poll_vote') }}"
    data-translate-poll-voters="{{ __('chats.poll_voters') }}"
    data-translate-poll-close="{{ __('chats.poll_close') }}"
    data-translate-poll-error="{{ __('chats.poll_error') }}"
    data-translate-poll-validation="{{ __('chats.poll_validation') }}"
    data-translate-video-note-record="{{ __('chats.video_note_record') }}"
    data-translate-video-note-stop="{{ __('chats.video_note_stop') }}"
    data-translate-video-note-error="{{ __('chats.video_note_error') }}"
></div>


{{-- =========================================================
     JAVASCRIPT
     ========================================================= --}}

@push('scripts')

    <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1/index.js"></script>
    @vite('resources/js/chat/index.js')

@endpush

@endsection