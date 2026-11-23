@extends('template')

@section('title_page', __('chats.chat'))

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

.chat-header {
    text-align: center;

    margin-bottom: 25px;

    padding-bottom: 20px;

    border-bottom: 1px solid rgba(255, 215, 0, 0.20);
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

</style>


<div class="chat-container">

    {{-- =====================================================
         ЗАГОЛОВОК ЧАТА
         ===================================================== --}}

    <div class="chat-header">

        @if ($chat->type === 'personal' && $otherUser)

            <h2 class="chat-title">
                {{ $otherUser->name }}
            </h2>

            <p class="chat-status">

                @if ($otherUser->isOnline())

                    <span class="status-indicator online"></span>

                    {{ __('chats.online') }}

                @else

                    <span class="status-indicator offline"></span>

                    {{ $otherUser->lastSeenHuman() }}

                @endif

            </p>

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

    </div>


    <div class="chat-members-panel">
        <details>
            <summary>
                {{ __('chats.participants') }} ({{ $chat->users->count() }})
            </summary>
            <div class="chat-members-list" id="chatMembersList">
                @foreach ($chat->users as $member)
                    <div class="chat-member" data-user-id="{{ $member->id }}">
                        <span class="chat-member-name">{{ $member->name }}</span>
                        <span class="chat-member-role">{{ $member->pivot->role ?? 'member' }}</span>

                        @if (in_array($currentRole, ['owner', 'admin'], true) && $member->id !== auth()->id() && ($member->pivot->role ?? 'member') !== 'owner' && ($currentRole === 'owner' || ($member->pivot->role ?? 'member') !== 'admin'))
                            @if ($currentRole === 'owner')
                                <select class="chat-member-role-select" aria-label="Role">
                                    <option value="member" @selected(($member->pivot->role ?? 'member') === 'member')>member</option>
                                    <option value="moderator" @selected(($member->pivot->role ?? 'member') === 'moderator')>moderator</option>
                                    <option value="admin" @selected(($member->pivot->role ?? 'member') === 'admin')>admin</option>
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

        @if (in_array($currentRole, ['owner', 'admin'], true) && $chat->type !== 'personal')
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

    <div class="chat-topics-bar" id="chatTopicsBar">
        <button type="button" class="chat-topic-tab active" data-topic-id="">
            {{ __('chats.all_messages') }}
        </button>

        @foreach ($topics as $topic)
            <button
                type="button"
                class="chat-topic-tab"
                data-topic-id="{{ $topic->id }}"
                title="{{ $topic->description }}"
            >
                {{ $topic->name }}
            </button>
        @endforeach

        @if (in_array($currentRole, ['owner', 'admin', 'moderator'], true) && $chat->type !== 'personal')
            <button type="button" class="chat-topic-create" id="createChatTopic">
                + {{ __('chats.new_topic') }}
            </button>
        @endif
    </div>

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


        <button
            type="button"
            id="cancelReply"
            class="cancel-reply"
        >
            ✕
        </button>

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

        <div class="input-group">

            <div class="input-wrapper">

                <textarea
                    id="messageInput"
                    name="message"
                    placeholder="{{ __('chats.type_message') }}"
                    rows="1"
                    required
                    @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
                ></textarea>

            </div>


            <button
                type="button"
                id="attachChatFileBtn"
                class="voice-record-btn"
                aria-label="{{ __('chats.attach_file') }}"
                title="{{ __('chats.attach_file') }}"
                @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
            >
                <i class="fa fa-paperclip" aria-hidden="true"></i>
            </button>

            <input
                type="file"
                id="chatAttachmentInput"
                accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                hidden
            >

            <button
                type="button"
                id="recordVoiceBtn"
                class="voice-record-btn"
                aria-label="{{ __('chats.voice_record') }}"
                title="{{ __('chats.voice_record') }}"
                @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
            >
                <i class="fa fa-microphone" aria-hidden="true"></i>
            </button>

            <button
                type="button"
                id="sendBtn"
                class="send-btn"
                @disabled($chat->type === 'channel' && !in_array($currentRole, ['owner', 'admin', 'moderator'], true))
            >

                <i
                    class="fa fa-paper-plane"
                    aria-hidden="true"
                ></i>

            </button>

        </div>

    </form>


    {{-- =====================================================
         ДОПОЛНИТЕЛЬНЫЕ КНОПКИ
         ===================================================== --}}

    <div class="additional-buttons">

        <a
            href="{{ route('chats.index') }}"
            class="chat-btn"
        >
            {{ __('chats.to_chats') }}
        </a>


        <a
            href="{{ route('chats.create') }}"
            class="chat-btn"
        >
            {{ __('chats.new_chat') }}
        </a>


        <a
            href="/notifications"
            class="chat-btn"
        >
            {{ __('chats.notifications') }}
        </a>

    </div>


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
    data-topic-id=""
    data-current-role="{{ $currentRole }}"
    data-user-id="{{ auth()->id() }}"
    data-user-name="{{ auth()->user()->name }}"
    data-first-unread="{{ $firstUnreadMessageId ?? '' }}"
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
    data-translate-voice-record="{{ __('chats.voice_record') }}"
    data-translate-voice-stop="{{ __('chats.voice_stop') }}"
    data-translate-attach-file="{{ __('chats.attach_file') }}"
    data-translate-media-loading="{{ __('chats.media_loading') }}"
    data-translate-media-error="{{ __('chats.media_error') }}"
    data-translate-media-download="{{ __('chats.media_download') }}"
></div>


{{-- =========================================================
     JAVASCRIPT
     ========================================================= --}}

@push('scripts')

    @vite('resources/js/chat/index.js')

@endpush

@endsection