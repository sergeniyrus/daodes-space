@extends('organizations.layouts.management')

@section('title_page')
    {{ __('organizations.management') }} — {{ $organization->name }}
@endsection

@section('management-content')

<style>

/* =========================================================
   ОСНОВНОЙ КОНТЕЙНЕР
   ========================================================= */

.organization-container {
    max-width: 1000px;
    margin: 35px auto 50px;
    padding: 25px;

    background:
        radial-gradient(
            circle at top right,
            rgba(255, 215, 0, 0.06),
            transparent 35%
        ),
        radial-gradient(
            circle at bottom left,
            rgba(0, 229, 255, 0.04),
            transparent 35%
        ),
        linear-gradient(
            145deg,
            #0b0c18 0%,
            #111323 50%,
            #0b0c18 100%
        );

    border: 1px solid rgba(255, 215, 0, 0.65);
    border-radius: 22px;

    color: #fff;

    box-shadow:
        0 14px 45px rgba(0, 0, 0, 0.55);

    overflow: hidden;
}


/* =========================================================
   ЗАГОЛОВОК
   ========================================================= */

.organization-header {
    position: relative;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 25px;

    margin-bottom: 24px;
    padding: 5px 5px 18px;

    border-bottom: 1px solid rgba(255, 215, 0, 0.20);
}


/* =========================================================
   ЛЕВАЯ ЧАСТЬ
   ========================================================= */

.organization-header-main {
    display: flex;
    align-items: baseline;

    gap: 18px;

    min-width: 0;
}


/* =========================================================
   УПРАВЛЕНИЕ ОРГАНИЗАЦИЕЙ
   ========================================================= */

.organization-management-title {
    color: #888;

    font-size: 0.85rem;
    font-weight: 500;

    white-space: nowrap;
}


/* =========================================================
   НАЗВАНИЕ ОРГАНИЗАЦИИ
   ========================================================= */

.organization-name {
    color: gold;

    font-size: 1.75rem;
    font-weight: 700;

    line-height: 1.2;

    word-break: break-word;

    text-shadow:
        0 0 10px rgba(255, 215, 0, 0.25);
}


/* =========================================================
   СТАТУС
   ========================================================= */

.organization-header-status {
    display: inline-flex;
    align-items: center;

    gap: 8px;

    flex-shrink: 0;

    color: #aaa;

    font-size: 0.9rem;

    white-space: nowrap;
}

.organization-status-indicator {
    width: 9px;
    height: 9px;

    flex-shrink: 0;

    border-radius: 50%;

    background-color: #4caf50;

    box-shadow:
        0 0 6px #4caf50,
        0 0 12px rgba(76, 175, 80, 0.35);
}


/* =========================================================
   ЦВЕТНАЯ ПОЛОСА
   ========================================================= */

.organization-header::after {
    content: "";

    position: absolute;

    left: 18%;
    right: 18%;

    bottom: -1px;

    height: 2px;

    background: linear-gradient(
        90deg,
        transparent,
        #00e5ff,
        gold,
        #00e5ff,
        transparent
    );

    box-shadow:
        0 0 10px rgba(0, 229, 255, 0.30),
        0 0 10px rgba(255, 215, 0, 0.25);
}


/* =========================================================
   УВЕДОМЛЕНИЯ
   ========================================================= */

.organization-alert {
    border-radius: 12px;

    padding: 10px 15px;

    margin-bottom: 15px;

    text-align: center;
}

.organization-alert.success {
    color: #4caf50;

    border: 1px solid #4caf50;

    background: rgba(76, 175, 80, 0.08);
}

.organization-alert.error {
    color: #ff6b6b;

    border: 1px solid #ff6b6b;

    background: rgba(255, 107, 107, 0.08);
}


/* =========================================================
   СЕКЦИЯ
   ========================================================= */

.organization-section {
    background:
        linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.025),
            rgba(255, 255, 255, 0.01)
        );

    border: 1px solid rgba(255, 215, 0, 0.25);

    border-radius: 15px;

    padding: 18px;

    margin-bottom: 15px;
}


/* =========================================================
   ЗАГОЛОВОК СЕКЦИИ
   ========================================================= */

.organization-activity-list { display: grid; gap: 9px; }
.organization-activity-item {
    padding: 11px 13px;
    border: 1px solid rgba(0, 229, 212, .18);
    border-radius: 10px;
    background: rgba(8, 16, 25, .48);
}
.organization-activity-item strong { display: block; color: #f0d68a; font-size: .9rem; }
.organization-activity-meta { display: flex; flex-wrap: wrap; gap: 7px 14px; margin-top: 5px; color: #929daf; font-size: .76rem; }
.organization-activity-item details { margin-top: 8px; color: #aeb9c8; font-size: .78rem; }
.organization-activity-item pre { overflow-x: auto; padding: 8px; color: #dce4e8; background: rgba(0,0,0,.24); border-radius: 7px; white-space: pre-wrap; }
.organization-section-title {
    color: gold;

    font-size: 1.15rem;

    margin: 0 0 15px;

    font-weight: 600;
}


/* =========================================================
   УЧАСТНИК
   ========================================================= */

.organization-member {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 20px;

    background: #2B2C2E;

    border: 1px solid #444;

    border-radius: 14px;

    padding: 14px 16px;

    margin-bottom: 10px;

    transition:
        border-color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.organization-member:hover {
    border-color: gold;

    transform: translateY(-1px);

    box-shadow:
        0 5px 18px rgba(0, 0, 0, 0.25);
}

.organization-member:last-child {
    margin-bottom: 0;
}


/* =========================================================
   ЛЕВАЯ ЧАСТЬ УЧАСТНИКА
   ========================================================= */

.organization-member-main {
    display: flex;

    align-items: center;

    gap: 13px;

    min-width: 0;

    flex: 1;
}


/* =========================================================
   АВАТАР
   ========================================================= */

.organization-member-avatar {
    flex: 0 0 auto;
}

.organization-member-avatar img {
    width: 48px;
    height: 48px;

    border-radius: 50%;

    object-fit: cover;

    border: 2px solid #00e5ff;

    box-shadow:
        0 0 10px rgba(0, 229, 255, 0.22);
}


/* =========================================================
   ИНФОРМАЦИЯ
   ========================================================= */

.organization-member-content {
    min-width: 0;

    flex: 1;
}

.organization-member-name {
    color: #fff;

    font-size: 1rem;
    font-weight: 600;

    margin-bottom: 7px;

    overflow: hidden;
    text-overflow: ellipsis;

    white-space: nowrap;
}

.organization-member-info {
    display: flex;

    flex-wrap: wrap;

    gap: 7px;
}


/* =========================================================
   BADGES
   ========================================================= */

.organization-badge {
    display: inline-block;

    padding: 4px 9px;

    border-radius: 12px;

    border: 1px solid #555;

    background: #313335;

    color: #ccc;

    font-size: 0.78rem;
}

.organization-badge.role {
    color: gold;

    border-color: gold;
}

.organization-badge.active {
    color: #4caf50;

    border-color: #4caf50;
}

.organization-badge.inactive {
    color: #ffb74d;

    border-color: #ffb74d;
}

.organization-badge.blocked {
    color: #ff6b6b;

    border-color: #ff6b6b;
}


/* =========================================================
   КНОПКИ УПРАВЛЕНИЯ
   ========================================================= */

.organization-member-actions {
    display: flex;

    align-items: center;
    justify-content: flex-end;

    flex-wrap: wrap;

    gap: 8px;

    flex-shrink: 0;
}


/* =========================================================
   SELECT УПРАВЛЕНИЯ
   ========================================================= */

.organization-action-select {
    min-height: 38px;

    padding: 0 30px 0 11px;

    background: #17181f;

    color: #ccc;

    border: 1px solid #555;

    border-radius: 8px;

    outline: none;

    cursor: pointer;

    font-size: 0.82rem;
}

.organization-action-select:hover {
    border-color: gold;

    color: gold;
}

.organization-action-select:focus {
    border-color: gold;

    box-shadow:
        0 0 7px rgba(255, 215, 0, 0.25);
}

.organization-action-select:disabled {
    opacity: 0.45;

    cursor: not-allowed;
}


/* =========================================================
   УДАЛЕНИЕ
   ========================================================= */

.organization-delete-button {
    min-height: 38px;

    padding: 0 13px;

    background: transparent;

    color: #ff6b6b;

    border: 1px solid #ff6b6b;

    border-radius: 8px;

    cursor: pointer;

    font-size: 0.82rem;

    transition:
        background 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease;
}

.organization-delete-button:hover {
    background: #ff5252;

    color: #fff;

    box-shadow:
        0 0 12px rgba(255, 82, 82, 0.3);
}

.organization-delete-button:disabled {
    opacity: 0.4;

    cursor: not-allowed;
}


/* =========================================================
   ДОБАВЛЕНИЕ СОТРУДНИКА
   ========================================================= */

.organization-add-form {
    display: flex;

    align-items: center;

    gap: 10px;
}

.organization-select {
    flex: 1;

    min-height: 46px;

    background: #1a1a1a;

    color: #fff;

    border: 1px solid gold;

    border-radius: 23px;

    padding: 0 16px;

    outline: none;

    font-size: 1rem;
}

.organization-select:focus {
    box-shadow:
        0 0 8px rgba(255, 215, 0, 0.35);
}

.organization-select option {
    background: #1a1a1a;

    color: #fff;
}


/* =========================================================
   ОСНОВНАЯ КНОПКА
   ========================================================= */

.organization-button {
    min-height: 46px;

    padding: 0 22px;

    background: #0b0c18;

    color: gold;

    border: 1px solid gold;

    border-radius: 23px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;
}

.organization-button:hover {
    transform: scale(1.03);

    box-shadow:
        0 0 10px rgba(255, 215, 0, 0.5);

    background: gold;

    color: #0b0c18;
}


/* =========================================================
   ПУСТО
   ========================================================= */

.organization-empty {
    color: #aaa;

    text-align: center;

    padding: 15px 0;
}


/* =========================================================
   НАЗАД
   ========================================================= */

.organization-back {
    display: block;

    width: fit-content;

    margin: 20px auto 0;

    padding: 9px 17px;

    background: #0b0c18;

    color: #00e5ff;

    border: 1px solid #00e5ff;

    border-radius: 20px;

    text-decoration: none;

    transition: 0.2s;
}

.organization-back:hover {
    background: #00e5ff;

    color: #0b0c18;
}


/* =========================================================
   АДАПТИВНОСТЬ
   ========================================================= */

@media (max-width: 800px) {

    .organization-member {
        flex-direction: column;

        align-items: stretch;
    }

    .organization-member-actions {
        width: 100%;

        justify-content: flex-start;
    }

    .organization-member-actions form {
        flex: 1;
    }

    .organization-action-select {
        width: 100%;
    }

    .organization-delete-button {
        width: 100%;
    }
}


@media (max-width: 700px) {

    .organization-container {
        margin: 20px 10px 35px;

        padding: 15px;

        border-radius: 17px;
    }

    .organization-header {
        display: block;

        text-align: center;

        padding-bottom: 15px;

        margin-bottom: 18px;
    }

    .organization-header-main {
        display: block;
    }

    .organization-management-title {
        font-size: 0.82rem;

        margin-bottom: 5px;
    }

    .organization-name {
        font-size: 1.4rem;
    }

    .organization-header-status {
        justify-content: center;

        margin-top: 8px;

        font-size: 0.82rem;
    }

    .organization-header::after {
        left: 10%;

        right: 10%;
    }

    .organization-section {
        padding: 14px;
    }

    .organization-add-form {
        flex-direction: column;

        align-items: stretch;
    }

    .organization-select,
    .organization-button {
        width: 100%;

        box-sizing: border-box;
    }
}


@media (max-width: 500px) {

    .organization-member-actions {
        flex-direction: column;

        align-items: stretch;
    }

    .organization-member-actions form {
        width: 100%;
    }

    .organization-action-select,
    .organization-delete-button {
        width: 100%;
    }
}


@media (max-width: 420px) {

    .organization-management-title {
        font-size: 0.78rem;
    }

    .organization-name {
        font-size: 1.25rem;
    }

    .organization-member-avatar img {
        width: 43px;

        height: 43px;
    }

    .organization-member-name {
        font-size: 0.92rem;
    }

    .organization-member-info {
        gap: 5px;
    }

    .organization-badge {
        font-size: 0.7rem;
    }
}


/* =========================================================
   ОТДЕЛЬНЫЙ ПЕРСОНАЛ: ПОВАРА / КУРЬЕРЫ / СОТРУДНИКИ
   ========================================================= */

.organization-staff-form {
    display: grid;
    grid-template-columns: minmax(180px, 1.2fr) minmax(180px, 1fr) minmax(150px, .8fr) auto;
    gap: 10px;
    align-items: center;
}

.organization-staff-input,
.organization-staff-select {
    width: 100%;
    min-width: 0;
    min-height: 44px;
    box-sizing: border-box;
    padding: 0 14px;
    background: #17181f;
    color: #fff;
    border: 1px solid rgba(0, 229, 255, .45);
    border-radius: 10px;
    outline: none;
}

.organization-staff-input:focus,
.organization-staff-select:focus {
    border-color: gold;
    box-shadow: 0 0 8px rgba(255, 215, 0, .2);
}

.organization-staff-select option {
    background: #17181f;
    color: #fff;
}

.organization-staff-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 15px;
    margin-bottom: 10px;
    background: linear-gradient(145deg, rgba(255,255,255,.035), rgba(255,255,255,.01));
    border: 1px solid rgba(0, 229, 255, .25);
    border-radius: 13px;
}

.organization-staff-card:last-child {
    margin-bottom: 0;
}

.organization-staff-main {
    min-width: 0;
    flex: 1;
}

.organization-staff-name {
    color: #fff;
    font-weight: 700;
    margin-bottom: 6px;
    overflow-wrap: anywhere;
}

.organization-staff-phone {
    color: #00e5ff;
    font-size: .9rem;
    margin-bottom: 9px;
}

.organization-staff-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.organization-staff-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

.organization-staff-actions form {
    margin: 0;
}

.organization-staff-action-select {
    min-height: 38px;
    max-width: 155px;
    padding: 0 9px;
    background: #17181f;
    color: #ddd;
    border: 1px solid #555;
    border-radius: 8px;
}

.organization-staff-action-select:focus {
    border-color: gold;
    outline: none;
}

.organization-staff-delete {
    min-height: 38px;
    padding: 0 12px;
    background: transparent;
    color: #ff7777;
    border: 1px solid #ff7777;
    border-radius: 8px;
    cursor: pointer;
}

.organization-staff-delete:hover {
    color: #fff;
    background: #d94444;
}

.organization-staff-note {
    margin-top: 12px;
    color: #999;
    font-size: .85rem;
    line-height: 1.5;
}

.organization-badge.pending {
    color: #ffca70;
    border-color: #ffca70;
}

.organization-badge.verified {
    color: #4caf50;
    border-color: #4caf50;
}

.organization-badge.unverified {
    color: #ffb74d;
    border-color: #ffb74d;
}

@media (max-width: 850px) {
    .organization-staff-form {
        grid-template-columns: 1fr 1fr;
    }

    .organization-staff-form button {
        width: 100%;
    }

    .organization-staff-card {
        align-items: stretch;
        flex-direction: column;
    }

    .organization-staff-actions {
        justify-content: flex-start;
    }
}

@media (max-width: 520px) {
    .organization-staff-form {
        grid-template-columns: 1fr;
    }

    .organization-staff-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .organization-staff-actions form,
    .organization-staff-action-select,
    .organization-staff-delete {
        width: 100%;
        max-width: none;
        box-sizing: border-box;
    }
}

/* =========================================================
   АНАЛИТИКА ОНЛАЙН-ЗАКАЗОВ
   ========================================================= */

.organization-order-dashboard {
    margin: 0 0 26px;
    padding: 20px;
    border: 1px solid rgba(0, 229, 255, 0.22);
    border-radius: 18px;
    background: linear-gradient(145deg, rgba(0, 229, 255, 0.055), rgba(255, 215, 0, 0.035));
}

.organization-order-dashboard-title {
    margin: 0 0 5px;
    color: #00e5ff;
    font-size: 1.12rem;
    font-weight: 700;
}

.organization-order-dashboard-subtitle {
    margin-bottom: 17px;
    color: #9298aa;
    font-size: 0.82rem;
}

.organization-order-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.organization-order-stat {
    min-width: 0;
    padding: 14px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 13px;
    background: rgba(7, 9, 19, 0.72);
}

.organization-order-stat-label {
    display: block;
    margin-bottom: 8px;
    color: #a7adbd;
    font-size: 0.78rem;
    line-height: 1.35;
}

.organization-order-stat-value {
    display: block;
    color: #f5d76e;
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

.organization-order-stat-value.turquoise {
    color: #00e5c3;
}

.organization-order-stat-value.muted {
    color: #c5cad5;
}

.organization-courier-loads {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-top: 16px;
}

.organization-courier-load {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 11px;
    border: 1px solid rgba(0, 229, 255, 0.18);
    border-radius: 10px;
    background: rgba(0, 229, 255, 0.045);
    color: #dce5ef;
    font-size: 0.82rem;
}

.organization-courier-load strong {
    color: #00e5c3;
}

@media (max-width: 760px) {
    .organization-order-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 420px) {
    .organization-order-dashboard {
        padding: 14px;
    }

    .organization-order-stats {
        gap: 8px;
    }

    .organization-order-stat {
        padding: 11px;
    }

    .organization-order-stat-value {
        font-size: 1.4rem;
    }
}


.organization-container{width:calc(100% - 32px);max-width:none;box-sizing:border-box;margin:22px auto 42px;padding:clamp(14px,2vw,28px);overflow:visible;background:radial-gradient(ellipse at 92% 0%,rgba(255,215,0,.09),transparent 34%),radial-gradient(ellipse at 0% 100%,rgba(0,229,212,.08),transparent 38%),linear-gradient(145deg,#080d18 0%,#101827 48%,#080d18 100%);border-color:rgba(0,229,212,.48);box-shadow:0 16px 48px rgba(0,0,0,.48),inset 0 1px 0 rgba(255,215,0,.14)}
.organization-header{border-bottom-color:rgba(0,229,212,.28)}.organization-name{color:#f5cf67}
.organization-tabs{position:sticky;top:8px;z-index:100;display:flex;align-items:center;gap:7px;margin:0 0 20px;padding:8px;overflow-x:auto;scrollbar-width:thin;background:rgba(7,13,24,.94);border:1px solid rgba(0,229,212,.32);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.3);backdrop-filter:blur(14px)}
.organization-tab{display:inline-flex;align-items:center;justify-content:center;gap:7px;flex:1 0 auto;min-height:42px;padding:0 15px;color:#aebdca;background:transparent;border:1px solid transparent;border-radius:10px;font:inherit;font-size:.88rem;font-weight:650;white-space:nowrap;cursor:pointer;transition:.18s ease}
.organization-tab span{color:#00e5d4;font-size:1.05rem}.organization-tab:hover{color:#fff;background:rgba(0,229,212,.07);border-color:rgba(0,229,212,.22)}
.organization-tab.is-active{color:#f5d76e;background:linear-gradient(135deg,rgba(0,229,212,.12),rgba(245,207,103,.10));border-color:rgba(245,207,103,.48);box-shadow:inset 0 0 14px rgba(0,229,212,.06),0 0 12px rgba(245,207,103,.06)}
.organization-tab-panel{display:none;min-width:0;animation:organizationTabIn .18s ease-out}.organization-tab-panel.is-active{display:block}
@keyframes organizationTabIn{from{opacity:.65;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
.organization-quick-links{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:14px}
.organization-quick-link{display:flex;flex-direction:column;align-items:flex-start;gap:8px;min-height:112px;padding:18px;color:#dce7ee;text-decoration:none;background:linear-gradient(145deg,rgba(20,34,49,.94),rgba(9,16,29,.96));border:1px solid rgba(0,229,212,.27);border-radius:14px;transition:.18s ease}
.organization-quick-link:hover{color:#fff;border-color:rgba(245,207,103,.7);box-shadow:0 0 18px rgba(0,229,212,.08);transform:translateY(-2px)}
.organization-quick-link strong{color:#f5cf67;font-size:1rem}.organization-quick-link small{color:#9aabba;line-height:1.45}
@media(max-width:700px){.organization-container{width:calc(100% - 12px);margin:10px auto 24px;padding:11px;border-radius:15px}.organization-tabs{top:4px;gap:5px;margin-bottom:14px;padding:6px;border-radius:12px}.organization-tab{min-height:40px;padding:0 12px;font-size:.8rem}}



/* Фоны отдельных TV-экранов */
.tv-background-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.tv-background-card {
    min-width: 0;
    padding: 14px;
    border: 1px solid rgba(0, 229, 255, .24);
    border-radius: 14px;
    background: rgba(5, 10, 20, .55);
}
.tv-background-card-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 12px;
    color: #f5d76e;
}
.tv-background-card-heading a { color: #00e5ff; font-size: .82rem; text-decoration: none; white-space: nowrap; }
.tv-background-preview, .tv-background-empty {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    border: 1px solid rgba(255, 215, 0, .25);
    border-radius: 9px;
    background: #101522;
}
.tv-background-empty {
    display: grid;
    place-items: center;
    color: #9da5b5;
    font-size: .9rem;
}
.tv-screen-settings {
    display: grid;
    gap: 7px;
    margin: 12px 0;
    padding: 11px;
    border: 1px solid rgba(0, 229, 212, .2);
    border-radius: 10px;
    background: rgba(0, 229, 212, .035);
}
.tv-screen-settings label { color: #d7dce6; font-size: .82rem; }
.tv-screen-settings input,
.tv-screen-settings select {
    width: 100%;
    min-width: 0;
    min-height: 38px;
    padding: 7px 9px;
    color: #f4f5f7;
    background: #171b24;
    border: 1px solid rgba(0, 229, 212, .3);
    border-radius: 8px;
}
.tv-screen-settings small { color: #9298aa; line-height: 1.4; }
.tv-screen-settings .organization-button { width: 100%; min-height: 38px; }
.tv-background-form { display: grid; gap: 8px; margin-top: 12px; }
.tv-background-form label { color: #d7dce6; font-size: .88rem; }
.tv-background-form input[type="file"] {
    width: 100%;
    min-width: 0;
    padding: 8px;
    color: #ddd;
    background: #17181f;
    border: 1px solid rgba(0, 229, 255, .3);
    border-radius: 8px;
    font-size: .8rem;
}
.tv-background-form small { color: #9298aa; }
.tv-background-form .organization-button { width: 100%; min-height: 40px; padding: 7px 12px; }
.tv-background-delete {
    width: 100%;
    min-height: 36px;
    margin-top: 8px;
    color: #ff8585;
    background: transparent;
    border: 1px solid rgba(255, 107, 107, .55);
    border-radius: 9px;
    cursor: pointer;
}
.tv-background-delete:hover { background: rgba(255, 107, 107, .12); }
@media (max-width: 850px) { .tv-background-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .tv-background-grid { grid-template-columns: 1fr; } }



.tv-management-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}
.tv-management-heading .organization-section-title { margin: 0; }
.tv-management-heading form { flex: 0 0 auto; }
.tv-management-heading .organization-button { white-space: nowrap; }
.tv-screen-delete {
    width: 100%;
    min-height: 36px;
    margin-top: 8px;
    color: #ff8585;
    background: rgba(100, 20, 25, .12);
    border: 1px solid rgba(255, 107, 107, .55);
    border-radius: 9px;
    cursor: pointer;
}
.tv-screen-delete:hover { background: rgba(255, 107, 107, .18); }
@media (max-width: 560px) {
    .tv-management-heading { align-items: flex-start; flex-direction: column; }
    .tv-management-heading form, .tv-management-heading .organization-button { width: 100%; }
}

</style>


    <section class="organization-tab-panel is-active" data-tab-panel="overview">
<section class="organization-order-dashboard">
        <h2 class="organization-order-dashboard-title">📊 Заказы и выручка</h2>
        <div class="organization-order-dashboard-subtitle">
            Операционная сводка за {{ now()->format('d.m.Y') }} и текущая загрузка
        </div>

        <div class="organization-order-stats">
            <div class="organization-order-stat" data-stat="orders-today">
                <span class="organization-order-stat-label">Заказов сегодня</span>
                <strong class="organization-order-stat-value">{{ (int) ($orderStats->orders_today ?? 0) }}</strong>
            </div>

            <div class="organization-order-stat" data-stat="new-orders">
                <span class="organization-order-stat-label">Новые</span>
                <strong class="organization-order-stat-value">{{ (int) ($orderStats->new_orders ?? 0) }}</strong>
            </div>

            <div class="organization-order-stat" data-stat="kitchen-orders">
                <span class="organization-order-stat-label">На кухне</span>
                <strong class="organization-order-stat-value">{{ (int) ($orderStats->kitchen_orders ?? 0) }}</strong>
            </div>

            <div class="organization-order-stat" data-stat="ready-orders">
                <span class="organization-order-stat-label">Готовы к выдаче</span>
                <strong class="organization-order-stat-value">{{ (int) ($orderStats->ready_orders ?? 0) }}</strong>
            </div>

            <div class="organization-order-stat" data-stat="delivery-orders">
                <span class="organization-order-stat-label">У курьеров</span>
                <strong class="organization-order-stat-value">{{ (int) ($orderStats->delivery_orders ?? 0) }}</strong>
            </div>

            <div class="organization-order-stat" data-stat="completed-today">
                <span class="organization-order-stat-label">Завершено сегодня</span>
                <strong class="organization-order-stat-value turquoise">{{ (int) ($orderStats->completed_today ?? 0) }}</strong>
            </div>

            <div class="organization-order-stat" data-stat="cancelled-today">
                <span class="organization-order-stat-label">Отменено сегодня</span>
                <strong class="organization-order-stat-value muted">{{ (int) ($orderStats->cancelled_today ?? 0) }}</strong>
            </div>

            <div class="organization-order-stat" data-stat="paid-today">
                <span class="organization-order-stat-label">Оплачено сегодня</span>
                <strong class="organization-order-stat-value turquoise">{{ number_format((float) $paidToday, 2, ',', ' ') }} {{ $orderSettings?->currency ?? 'EUR' }}</strong>
            </div>
        </div>

        @if($courierMemberships->isNotEmpty())
            <div class="organization-courier-loads">
                @foreach($courierMemberships as $membership)
                    <div class="organization-courier-load">
                        <span>🚚 {{ $membership->employee->name }}</span>
                        <strong>{{ $membership->employee->active_delivery_orders_count }} в доставке</strong>
                    </div>
                @endforeach
            </div>
        @endif

        <a
            href="{{ route('organizations.orders.index', $organization) }}"
            class="organization-button"
            style="display: inline-flex; margin-top: 16px; text-decoration: none;"
        >
            📋 Открыть все заказы
        </a>
    </section>

    <section class="organization-section">
        <h2 class="organization-section-title">История действий</h2>
        @if($activityLogs->isEmpty())
            <div class="organization-empty">Пока нет записей об изменениях организации.</div>
        @else
            <div class="organization-activity-list">
                @foreach($activityLogs as $activity)
                    <article class="organization-activity-item">
                        <strong>{{ $activity->description }}</strong>
                        <div class="organization-activity-meta">
                            <span>{{ $activity->actor?->name ?? 'Система' }}</span>
                            <span>{{ $activity->created_at?->timezone('Asia/Krasnoyarsk')->format('d.m.Y H:i') }}</span>
                        </div>
                        @if($activity->before_values || $activity->after_values)
                            <details>
                                <summary>Посмотреть изменения</summary>
                                <pre>{{ json_encode(['до' => $activity->before_values, 'после' => $activity->after_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</section>


    <section class="organization-tab-panel" data-tab-panel="team">
{{-- =====================================================
         УЧАСТНИКИ
         ===================================================== --}}

    <div class="organization-section">

        <h2 class="organization-section-title">
            {{ __('organizations.members') }}
        </h2>


        @forelse($members as $member)

            <div class="organization-member">

                <div class="organization-member-main">

                    <div class="organization-member-avatar">

                        <img
                            src="{{ $member->profile?->avatar_url ?? '/img/main/default-avatar.png' }}"
                            alt="{{ $member->name }}"
                        >

                    </div>

                    <div class="organization-member-content">

                        <div class="organization-member-name">
                            {{ $member->name }}
                        </div>

                        <div class="organization-member-info">

                            <span class="organization-badge role">
                                {{ __('organizations.roles.' . $member->pivot->role) }}
                            </span>

                            <span class="organization-badge {{ $member->pivot->status }}">
                                {{ __('organizations.statuses.' . $member->pivot->status) }}
                            </span>

                        </div>

                    </div>

                </div>


                {{-- =================================================
                     УПРАВЛЕНИЕ СОТРУДНИКОМ
                     ================================================= --}}

                <div class="organization-member-actions">


                    {{-- =================================================
                         ИЗМЕНЕНИЕ РОЛИ
                         ================================================= --}}

                    <form
                        method="POST"
                        action="{{ route('organizations.employees.role', [
                            'organization' => $organization,
                            'user' => $member
                        ]) }}"
                    >

                        @csrf

                        <select
                            name="role"
                            class="organization-action-select"
                            onchange="this.form.submit()"
                            @disabled(auth()->id() === $member->id)
                            aria-label="{{ __('organizations.actions.change_role') }}"
                        >

                            <option
                                value="employee"
                                @selected($member->pivot->role === 'employee')
                            >
                                {{ __('organizations.roles.employee') }}
                            </option>

                            <option
                                value="manager"
                                @selected($member->pivot->role === 'manager')
                            >
                                {{ __('organizations.roles.manager') }}
                            </option>

                        </select>

                    </form>


                    {{-- =================================================
                         ИЗМЕНЕНИЕ СТАТУСА
                         ================================================= --}}

                    <form
                        method="POST"
                        action="{{ route('organizations.employees.status', [
                            'organization' => $organization,
                            'user' => $member
                        ]) }}"
                    >

                        @csrf

                        <select
                            name="status"
                            class="organization-action-select"
                            onchange="this.form.submit()"
                            @disabled(auth()->id() === $member->id)
                            aria-label="{{ __('organizations.actions.change_status') }}"
                        >

                            <option
                                value="active"
                                @selected($member->pivot->status === 'active')
                            >
                                {{ __('organizations.statuses.active') }}
                            </option>

                            <option
                                value="inactive"
                                @selected($member->pivot->status === 'inactive')
                            >
                                {{ __('organizations.statuses.inactive') }}
                            </option>

                            <option
                                value="blocked"
                                @selected($member->pivot->status === 'blocked')
                            >
                                {{ __('organizations.statuses.blocked') }}
                            </option>

                        </select>

                    </form>


                    {{-- =================================================
                         УДАЛЕНИЕ
                         ================================================= --}}

                    <form
                        method="POST"
                        action="{{ route('organizations.employees.destroy', [
                            'organization' => $organization,
                            'user' => $member
                        ]) }}"
                        onsubmit="return confirm('{{ __('organizations.messages.remove_confirm') }}');"
                    >

                        @csrf

                        @method('DELETE')

                        <button
                            type="submit"
                            class="organization-delete-button"
                            @disabled(auth()->id() === $member->id)
                        >
                            {{ __('organizations.actions.remove') }}
                        </button>

                    </form>

                </div>

            </div>

        @empty

            <div class="organization-empty">
                {{ __('organizations.no_members') }}
            </div>

        @endforelse

    </div>


    {{-- =====================================================
         ДОБАВЛЕНИЕ СОТРУДНИКА
         ===================================================== --}}

    <div class="organization-section">

        <h2 class="organization-section-title">
            {{ __('organizations.add_employee') }}
        </h2>


        <form
            method="GET"
            action="{{ route('organizations.manage', $organization) }}"
            class="organization-add-form"
            style="margin-bottom: 12px;"
        >
            <input
                type="search"
                name="user_search"
                class="organization-select"
                value="{{ request('user_search') }}"
                placeholder="Поиск пользователя DAODES..."
                aria-label="Поиск пользователя DAODES"
            >
            <button type="submit" class="organization-button">Найти</button>
            @if(request()->filled('user_search'))
                <a href="{{ route('organizations.manage', $organization) }}" class="organization-button" style="text-decoration:none;">Сбросить</a>
            @endif
        </form>

        @if($availableUsers->isNotEmpty())

            <form
                method="POST"
                action="{{ route('organizations.employees.store', $organization) }}"
                class="organization-add-form"
            >

                @csrf

                <select
                    name="user_id"
                    class="organization-select"
                    required
                >

                    <option value="">
                        {{ __('organizations.select_user') }}
                    </option>

                    @foreach($availableUsers as $availableUser)

                        <option value="{{ $availableUser->id }}">
                            {{ $availableUser->name }}
                        </option>

                    @endforeach

                </select>

                <button
                    type="submit"
                    class="organization-button"
                >
                    {{ __('organizations.add_employee') }}
                </button>

            </form>

            <div class="organization-pagination">
                {{ $availableUsers->links() }}
            </div>

        @else

            <div class="organization-empty">
                {{ request()->filled('user_search') ? 'По вашему запросу пользователи не найдены.' : __('organizations.no_available_users') }}
            </div>

        @endif

    </div>

    {{-- =====================================================
         ОТДЕЛЬНЫЙ ПЕРСОНАЛ ОРГАНИЗАЦИИ
         ===================================================== --}}

    <div class="organization-section">

        <h2 class="organization-section-title">
            👥 Персонал организации
        </h2>

        <form
            method="POST"
            action="{{ route('organizations.staff.store', $organization) }}"
            class="organization-staff-form"
        >
            @csrf

            <input
                type="text"
                name="name"
                class="organization-staff-input"
                value="{{ old('name') }}"
                placeholder="Имя сотрудника"
                maxlength="100"
                required
            >

            <input
                type="tel"
                name="phone"
                class="organization-staff-input"
                value="{{ old('phone') }}"
                placeholder="+79991234567"
                maxlength="32"
                autocomplete="tel"
                required
            >

            <select
                name="role"
                class="organization-staff-select"
                required
            >
                <option value="employee" @selected(old('role', 'employee') === 'employee')>
                    👤 Сотрудник
                </option>
                <option value="admin" @selected(old('role') === 'admin')>
                    🛡 Администратор
                </option>
                <option value="cook" @selected(old('role') === 'cook')>
                    👨‍🍳 Повар
                </option>
                <option value="courier" @selected(old('role') === 'courier')>
                    🚚 Курьер
                </option>
            </select>

            <button type="submit" class="organization-button">
                Добавить
            </button>
        </form>

        <div class="organization-staff-note">
            Указывайте телефон в международном формате E.164, например +79991234567.
            Если сотрудник уже работает в DAODES или другой организации, будет использована его существующая учётная запись.
            Новый сотрудник получит доступ только после подтверждения телефона.
        </div>

        <div style="margin-top: 20px;">
            @forelse($staffMemberships as $staffMembership)
                @php
                    $staffEmployee = $staffMembership->employee;
                @endphp

                @if($staffEmployee)
                    <div class="organization-staff-card">
                        <div class="organization-staff-main">
                            <div class="organization-staff-name">
                                {{ $staffEmployee->name }}
                            </div>

                            <div class="organization-staff-phone">
                                {{ $staffEmployee->phone }}
                            </div>

                            <div class="organization-staff-meta">
                                <span class="organization-badge role">
                                    @switch($staffMembership->role)
                                        @case('cook')
                                            👨‍🍳 Повар
                                            @break
                                        @case('courier')
                                            🚚 Курьер
                                            @break
                                        @case('admin')
                                            🛡 Администратор
                                            @break
                                        @default
                                            👤 Сотрудник
                                    @endswitch
                                </span>

                                <span class="organization-badge {{ $staffMembership->status }}">
                                    @switch($staffMembership->status)
                                        @case('active')
                                            Активен
                                            @break
                                        @case('blocked')
                                            Заблокирован
                                            @break
                                        @default
                                            Ожидает активации
                                    @endswitch
                                </span>

                                <span class="organization-badge {{ $staffEmployee->phone_verified_at ? 'verified' : 'unverified' }}">
                                    {{ $staffEmployee->phone_verified_at ? 'Телефон подтверждён' : 'Телефон не подтверждён' }}
                                </span>
                            </div>
                        </div>

                        <div class="organization-staff-actions">
                            @if(!$staffEmployee->phone_verified_at && $staffMembership->status === 'pending')
                                <form
                                    method="POST"
                                    action="{{ route('organizations.staff.invite', [$organization, $staffEmployee]) }}"
                                >
                                    @csrf
                                    <button type="submit" class="organization-button">
                                        Отправить приглашение повторно
                                    </button>
                                </form>
                            @endif

                            <form
                                method="POST"
                                action="{{ route('organizations.staff.role', [$organization, $staffEmployee]) }}"
                            >
                                @csrf
                                @method('PATCH')

                                <select
                                    name="role"
                                    class="organization-staff-action-select"
                                    onchange="this.form.submit()"
                                    aria-label="Изменить роль"
                                >
                                    <option value="employee" @selected($staffMembership->role === 'employee')>
                                        Сотрудник
                                    </option>
                                    <option value="admin" @selected($staffMembership->role === 'admin')>
                                        Администратор
                                    </option>
                                    <option value="cook" @selected($staffMembership->role === 'cook')>
                                        Повар
                                    </option>
                                    <option value="courier" @selected($staffMembership->role === 'courier')>
                                        Курьер
                                    </option>
                                </select>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('organizations.staff.status', [$organization, $staffEmployee]) }}"
                            >
                                @csrf
                                @method('PATCH')

                                <select
                                    name="status"
                                    class="organization-staff-action-select"
                                    onchange="this.form.submit()"
                                    aria-label="Изменить статус доступа"
                                >
                                    <option value="pending" @selected($staffMembership->status === 'pending')>
                                        Ожидает
                                    </option>
                                    <option value="active" @selected($staffMembership->status === 'active')>
                                        Активировать
                                    </option>
                                    <option value="blocked" @selected($staffMembership->status === 'blocked')>
                                        Заблокировать
                                    </option>
                                </select>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('organizations.staff.destroy', [$organization, $staffEmployee]) }}"
                                onsubmit="return confirm('Удалить сотрудника из этой организации? Его аккаунт и доступ к другим организациям сохранятся.');"
                            >
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="organization-staff-delete">
                                    Удалить
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            @empty
                <div class="organization-empty">
                    В этой организации пока нет сотрудников.
                </div>
            @endforelse
        </div>

    </div>

</section>

{{-- =====================================================
     НАСТРОЙКИ ОНЛАЙН-ЗАКАЗОВ
     ===================================================== --}}
<style>
.organization-order-settings {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr));
    gap: 10px 14px;
    margin-top: 12px;
    align-items: end;
}
.organization-order-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
    color: #d8dce8;
    font-size: .86rem;
    font-weight: 650;
}
.organization-order-field--compact {
    width: min(100%, 220px);
    min-width: 0;
    white-space: nowrap;
}
.organization-order-input {
    box-sizing: border-box;
    width: 100%;
    min-height: 38px;
    padding: 7px 10px;
    color: #fff;
    background: #090c16;
    border: 1px solid rgba(0, 229, 212, .45);
    border-radius: 8px;
}
.organization-order-input:focus {
    border-color: gold;
    outline: none;
    box-shadow: 0 0 8px rgba(255, 215, 0, .18);
}
.organization-order-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
    gap: 10px 16px;
    margin-top: 18px;
}
.organization-order-options label {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #d8dce8;
}
.organization-order-options input {
    accent-color: #00e5d4;
}
.organization-order-hint {
    margin-top: 10px;
    color: #9da7b8;
    font-size: .82rem;
    line-height: 1.4;
}
.organization-order-hint ul {
    display: flex;
    flex-wrap: wrap;
    gap: 5px 20px;
    margin: 7px 0 0;
    padding-left: 18px;
}
.organization-order-options {
    gap: 8px 14px;
    margin-top: 12px;
    padding: 10px 12px;
    background: rgba(0,229,212,.035);
    border: 1px solid rgba(0,229,212,.13);
    border-radius: 11px;
}
.organization-order-options label {font-size:.88rem}
.organization-order-error {
    color: #ff8585;
    font-size: .85rem;
}

.organization-delivery-zones{margin:18px 0 8px;padding:18px;background:linear-gradient(145deg,rgba(0,229,212,.075),rgba(255,215,0,.035));border:1px solid rgba(0,229,212,.34);border-radius:16px}
.organization-delivery-zones[hidden]{display:none!important}
.organization-delivery-zones-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
.organization-delivery-zones-heading h3{margin:0;color:#ffd700;font-size:1.12rem}
.organization-delivery-zones-heading p{margin:5px 0 0;color:#aeb5c4;font-size:.88rem}
.organization-delivery-limit{flex:0 0 auto;padding:6px 10px;color:#00e5d4;border:1px solid rgba(0,229,212,.35);border-radius:20px;font-size:.8rem;font-weight:700}
.organization-delivery-origin-map{height:430px;min-height:360px;margin:10px 0 14px;border:1px solid rgba(0,229,212,.4);border-radius:12px;overflow:hidden;background:#101522}
.organization-delivery-map-tools{display:flex;align-items:center;flex-wrap:wrap;gap:10px;color:#aeb5c4;font-size:.86rem}
.organization-delivery-map-button,.organization-delivery-zone-add{min-height:36px;padding:7px 13px;color:#00e5d4;background:rgba(0,229,212,.07);border:1px solid rgba(0,229,212,.45);border-radius:20px;font-weight:700;cursor:pointer}
.organization-delivery-zones-list{display:grid;gap:8px;margin-top:14px}
.organization-delivery-zone-row{display:grid;grid-template-columns:34px minmax(100px,.8fr) minmax(150px,1.2fr) 36px;align-items:end;gap:10px;padding:10px;background:rgba(5,10,20,.58);border:1px solid rgba(255,215,0,.18);border-radius:11px}
.organization-delivery-zone-number{align-self:center;color:#ffd700;font-weight:800;text-align:center}
.organization-delivery-zone-row label{display:grid;gap:5px;color:#aeb5c4;font-size:.78rem}
.organization-delivery-zone-row input{width:100%;min-height:38px;box-sizing:border-box;padding:7px 9px;color:#fff;background:#090c16;border:1px solid rgba(0,229,212,.4);border-radius:8px}
.organization-delivery-zone-remove{width:34px;height:36px;color:#ff8d8d;background:rgba(255,90,90,.08);border:1px solid rgba(255,90,90,.3);border-radius:9px;font-size:1.35rem;cursor:pointer}
.organization-delivery-zones-note{margin-top:12px;color:#9da7b8;font-size:.84rem;line-height:1.45}
@media(max-width:600px){.organization-delivery-origin-map{height:360px;min-height:320px}.organization-delivery-zones{padding:12px}.organization-delivery-zones-heading{flex-direction:column}.organization-delivery-zone-row{grid-template-columns:28px minmax(0,1fr) minmax(0,1fr) 32px;gap:6px;padding:7px}.organization-delivery-zone-row label{font-size:.7rem}.organization-delivery-zone-row input{min-height:36px;padding:5px}.organization-delivery-zone-remove{width:30px}}
</style>

<section class="organization-tab-panel" data-tab-panel="settings">
<div class="organization-section">
    <h2 class="organization-section-title">
        🛒 Настройки онлайн-заказов
    </h2>

    <form
        method="POST"
        action="{{ route('organizations.order-settings.update', $organization) }}"
    >
        @csrf
        @method('PUT')

        <div class="organization-order-settings">
            <label class="organization-order-field organization-order-field--compact">
                Валюта (ISO 4217)
                <input
                    type="text"
                    name="currency"
                    class="organization-order-input"
                    value="{{ old('currency', $orderSettings?->currency) }}"
                    maxlength="3"
                    minlength="3"
                    pattern="[A-Za-z]{3}"
                    placeholder="EUR"
                    autocomplete="off"
                >
                @error('currency')
                    <span class="organization-order-error">{{ $message }}</span>
                @enderror
            </label>

            @php
                $workingHoursValues = old('working_hours', $orderSettings?->working_hours ?? []);
                $workingDays = [
                    1 => 'Понедельник',
                    2 => 'Вторник',
                    3 => 'Среда',
                    4 => 'Четверг',
                    5 => 'Пятница',
                    6 => 'Суббота',
                    7 => 'Воскресенье',
                ];
            @endphp

            <section class="organization-order-schedule" style="grid-column: 1 / -1; padding: 16px; border: 1px solid rgba(0,229,212,.3); border-radius: 12px; background: rgba(0,229,212,.035);">
                <h3 style="margin: 0 0 6px; color: #00e5d4;">🕒 График работы заведения</h3>
                <p style="margin: 0 0 12px; color: #aeb5c4; font-size: .88rem;">Заказы и предзаказы будут доступны только в указанные часы. Можно задать ночной график, например с 18:00 до 02:00. Пока график не сохранён, оформление заказов недоступно.</p>
                <div style="display:grid; gap:8px;">
                    @foreach($workingDays as $dayNumber => $dayName)
                        @php
                            $dayHours = $workingHoursValues[$dayNumber] ?? [];
                            $dayEnabled = old("working_hours.$dayNumber.enabled", $dayHours['enabled'] ?? ($orderSettings?->working_hours === null));
                        @endphp
                        <div style="display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:9px; padding:8px 10px; border:1px solid rgba(255,215,0,.12); border-radius:9px;">
                            <strong style="color:#e8eaf0;">{{ $dayName }}</strong>
                            <label style="display:flex; align-items:center; gap:6px; color:#00e5d4; white-space:nowrap;">
                                <input type="checkbox" name="working_hours[{{ $dayNumber }}][enabled]" value="1" @checked($dayEnabled)>
                                Работает
                            </label>
                            <label style="color:#aeb5c4; font-size:.82rem;">Открытие
                                <input type="time" name="working_hours[{{ $dayNumber }}][open]" value="{{ old("working_hours.$dayNumber.open", $dayHours['open'] ?? '09:00') }}" class="organization-order-input">
                            </label>
                            <label style="color:#aeb5c4; font-size:.82rem;">Закрытие
                                <input type="time" name="working_hours[{{ $dayNumber }}][close]" value="{{ old("working_hours.$dayNumber.close", $dayHours['close'] ?? '22:00') }}" class="organization-order-input">
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('working_hours')
                    <div class="organization-order-error">{{ $message }}</div>
                @enderror
                @foreach(range(1, 7) as $dayNumber)
                    @error("working_hours.$dayNumber.open")<div class="organization-order-error">{{ $message }}</div>@enderror
                    @error("working_hours.$dayNumber.close")<div class="organization-order-error">{{ $message }}</div>@enderror
                @endforeach
            </section>

            <label class="organization-order-field">
                Часовой пояс заведения (IANA)
                <input type="text" name="timezone" class="organization-order-input"
                    value="{{ old('timezone', $orderSettings?->timezone ?: 'Asia/Krasnoyarsk') }}"
                    placeholder="Europe/Sofia" required>
                <small style="color:#9ca3af;">Например: Europe/Sofia, Europe/Moscow, Asia/Krasnoyarsk.</small>
                @error('timezone')<span class="organization-order-error">{{ $message }}</span>@enderror
            </label>

            <label class="organization-order-field">
                Минимум до заказа (минут)
                <input type="number" name="order_lead_minutes" class="organization-order-input"
                    value="{{ old('order_lead_minutes', $orderSettings?->order_lead_minutes ?? 30) }}"
                    min="30" max="1440" step="1" required>
                @error('order_lead_minutes')<span class="organization-order-error">{{ $message }}</span>@enderror
            </label>

            <label class="organization-order-field">
                Шаг времени предзаказа (минут)
                <select name="time_slot_minutes" class="organization-order-input">
                    @foreach([5, 10, 15, 20, 30, 60] as $slot)
                        <option value="{{ $slot }}" @selected((int) old('time_slot_minutes', $orderSettings?->time_slot_minutes ?? 15) === $slot)>{{ $slot }} минут</option>
                    @endforeach
                </select>
            </label>

            <label class="organization-order-field" style="display:flex; flex-direction:row; align-items:center; gap:9px;">
                <input type="checkbox" name="preorders_enabled" value="1" @checked(old('preorders_enabled', $orderSettings?->preorders_enabled ?? true))>
                <span>Разрешить предзаказ на дату и время</span>
            </label>

            <label class="organization-order-field">
                Минимальное время самовывоза (мин.)
                <input
                    type="number"
                    name="pickup_min_minutes"
                    class="organization-order-input"
                    value="{{ old('pickup_min_minutes', $orderSettings?->pickup_min_minutes) }}"
                    min="0"
                    max="1440"
                    step="1"
                    placeholder="Не задано"
                >
                @error('pickup_min_minutes')
                    <span class="organization-order-error">{{ $message }}</span>
                @enderror
            </label>

            <label class="organization-order-field">
                Город и регион доставки по умолчанию
                <input type="text" name="delivery_city" class="organization-order-input"
                    value="{{ old('delivery_city', $orderSettings?->delivery_city) }}"
                    maxlength="255" placeholder="Зеленогорск, Красноярский край, Россия">
                @error('delivery_city')<span class="organization-order-error">{{ $message }}</span>@enderror
            </label>

            <label class="organization-order-field">
                Адрес заведения (самовывоз / центр доставки)
                <input type="text" name="pickup_address" id="organization-pickup-address" class="organization-order-input"
                    value="{{ old('pickup_address', $orderSettings?->pickup_address) }}"
                    maxlength="500" placeholder="Улица, дом, город">
                <small style="color:#9ca3af;">Укажите адрес, если включены самовывоз или доставка. От этой точки рассчитываются зоны доставки.</small>
                @error('pickup_address')<span class="organization-order-error">{{ $message }}</span>@enderror
            </label>
        </div>

        <section class="organization-delivery-zones" id="organization-delivery-zones" @if(!old('delivery_enabled', $orderSettings?->delivery_enabled ?? false)) hidden @endif>
            <div class="organization-delivery-zones-heading">
                <div>
                    <h3>🚚 Зоны доставки</h3>
                    <p>Расстояние считается от адреса самовывоза. До 10 км включительно; дальше доставка недоступна.</p>
                </div>
                <span class="organization-delivery-limit">Максимум 10 км</span>
            </div>
            <input type="hidden" name="delivery_origin_latitude" id="organization-delivery-origin-latitude" value="{{ old('delivery_origin_latitude', $orderSettings?->delivery_origin_latitude) }}">
            <input type="hidden" name="delivery_origin_longitude" id="organization-delivery-origin-longitude" value="{{ old('delivery_origin_longitude', $orderSettings?->delivery_origin_longitude) }}">
            <div class="organization-delivery-map-tools">
                <button type="button" class="organization-delivery-map-button" id="organization-delivery-find-address">Найти адрес на карте</button>
                <span id="organization-delivery-origin-status">
                    @if($orderSettings?->delivery_origin_latitude !== null && $orderSettings?->delivery_origin_longitude !== null)
                        Точка центра доставки сохранена
                    @else
                        Укажите точку заведения на карте
                    @endif
                </span>
            </div>
            <div id="organization-delivery-origin-map" class="organization-delivery-origin-map"></div>
            @error('delivery_origin_latitude')<div class="organization-order-error">{{ $message }}</div>@enderror
            @error('delivery_origin_longitude')<div class="organization-order-error">{{ $message }}</div>@enderror

            <div class="organization-delivery-zones-list" id="organization-delivery-zones-list">
                @php
                    $deliveryZoneRows = old('delivery_zones');
                    if (!is_array($deliveryZoneRows)) {
                        $deliveryZoneRows = $orderSettings?->deliveryZones?->map(fn ($zone) => [
                            'max_distance_km' => $zone->max_distance_km,
                            'minimum_order_amount' => $zone->minimum_order_amount,
                        ])->all() ?? [];
                    }
                    if ($deliveryZoneRows === []) {
                        $deliveryZoneRows = [
                            ['max_distance_km' => '2.00', 'minimum_order_amount' => '0.00'],
                            ['max_distance_km' => '5.00', 'minimum_order_amount' => '0.00'],
                            ['max_distance_km' => '7.00', 'minimum_order_amount' => '0.00'],
                            ['max_distance_km' => '10.00', 'minimum_order_amount' => '0.00'],
                        ];
                    }
                @endphp
                @foreach($deliveryZoneRows as $zoneIndex => $zone)
                    <div class="organization-delivery-zone-row" data-zone-row>
                        <span class="organization-delivery-zone-number">{{ $loop->iteration }}</span>
                        <label><span>До, км</span>
                            <input type="number" name="delivery_zones[{{ $zoneIndex }}][max_distance_km]" value="{{ $zone['max_distance_km'] ?? '' }}" min="0.01" max="10" step="0.01" required>
                        </label>
                        <label><span>Минимум корзины</span>
                            <input type="number" name="delivery_zones[{{ $zoneIndex }}][minimum_order_amount]" value="{{ $zone['minimum_order_amount'] ?? '0.00' }}" min="0" max="9999999999.99" step="0.01" required>
                        </label>
                        <button type="button" class="organization-delivery-zone-remove" aria-label="Удалить зону" title="Удалить зону">×</button>
                    </div>
                @endforeach
            </div>
            <button type="button" class="organization-delivery-zone-add" id="organization-delivery-zone-add">＋ Добавить зону</button>
            @error('delivery_zones')<div class="organization-order-error">{{ $message }}</div>@enderror
            <div class="organization-delivery-zones-note">Сумма в каждой зоне — минимальная стоимость блюд в корзине. Отдельной платы за доставку нет. Последняя зона должна заканчиваться на 10 км.</div>
        </section>

        <div class="organization-order-options">
            <label>
                <input
                    type="checkbox"
                    name="delivery_enabled"
                    value="1"
                    @checked(old('delivery_enabled', $orderSettings?->delivery_enabled ?? false))
                >
                🚚 Доставка
            </label>

            <label>
                <input
                    type="checkbox"
                    name="pickup_enabled"
                    value="1"
                    @checked(old('pickup_enabled', $orderSettings?->pickup_enabled ?? false))
                >
                🛍 Самовывоз
            </label>

            <label>
                <input
                    type="checkbox"
                    name="dine_in_enabled"
                    value="1"
                    @checked(old('dine_in_enabled', $orderSettings?->dine_in_enabled ?? false))
                >
                🍽 Заказ в заведении
            </label>
        </div>

        <div class="organization-order-options">
            <label>
                <input
                    type="checkbox"
                    name="online_payment_enabled"
                    value="1"
                    @checked(old('online_payment_enabled', $orderSettings?->online_payment_enabled ?? false))
                >
                💳 Онлайн-оплата
            </label>

            <label>
                <input
                    type="checkbox"
                    name="cash_payment_enabled"
                    value="1"
                    @checked(old('cash_payment_enabled', $orderSettings?->cash_payment_enabled ?? false))
                >
                💵 Наличные при получении
            </label>

            <label>
                <input
                    type="checkbox"
                    name="card_payment_enabled"
                    value="1"
                    @checked(old('card_payment_enabled', $orderSettings?->card_payment_enabled ?? false))
                >
                💳 Терминал при получении
            </label>
        </div>

        <div class="organization-order-options">
            <label>
                <input
                    type="checkbox"
                    name="orders_enabled"
                    value="1"
                    @checked(old('orders_enabled', $orderSettings?->orders_enabled ?? false))
                >
                <strong style="color: gold;">Принимать онлайн-заказы</strong>
            </label>
        </div>

        @error('delivery_enabled')
            <div class="organization-order-error">{{ $message }}</div>
        @enderror
        @error('cash_payment_enabled')
            <div class="organization-order-error">{{ $message }}</div>
        @enderror

        <div class="organization-order-hint">
            Для доставки и самовывоза нужна оплата наличными или терминалом при получении. Для заказа в заведении используется гостевой счёт — расчёт на кассе при выходе. Онлайн-оплата станет доступна после подключения платёжного провайдера.
        </div>

        <div class="organization-order-hint">
            <strong style="color: #f5d76e;">Проверка готовности онлайн-заказов:</strong>
            <ul style="margin: 8px 0 0; padding-left: 20px;">
                <li>
                    @if(filled($orderSettings?->currency))
                        <span style="color: #00e5a8;">✓ Валюта указана ({{ $orderSettings->currency }})</span>
                    @else
                        <span style="color: #ffb74d;">○ Укажите валюту (например, EUR или RUB)</span>
                    @endif
                </li>
                <li>
                    @if($orderSettings?->hasConfiguredWorkingHours())
                        <span style="color: #00e5a8;">✓ График работы настроен</span>
                    @else
                        <span style="color: #ffb74d;">○ Настройте рабочие дни и часы</span>
                    @endif
                </li>
                <li>
                    @if($orderSettings?->orders_enabled)
                        <span style="color: #00e5a8;">✓ Приём онлайн-заказов включён</span>
                    @else
                        <span style="color: #ffb74d;">○ Включите «Принимать онлайн-заказы»</span>
                    @endif
                </li>
                @php
                    $hasReceiptPayment = $orderSettings
                        && ($orderSettings->cash_payment_enabled || $orderSettings->card_payment_enabled);
                    $hasValidDelivery = $orderSettings?->hasConfiguredDelivery() && $hasReceiptPayment;
                    $hasValidPickup = $orderSettings?->pickup_enabled
                        && $orderSettings->hasPickupAddress()
                        && $hasReceiptPayment;
                    $hasValidDineIn = (bool) $orderSettings?->dine_in_enabled;
                @endphp
                @if($orderSettings?->delivery_enabled)
                    <li>
                        @if($hasValidDelivery)
                            <span style="color: #00e5a8;">✓ Доставка: точка, зоны и оплата настроены</span>
                        @else
                            <span style="color: #ffb74d;">○ Для доставки настройте точку, зоны и наличные/терминал</span>
                        @endif
                    </li>
                @endif
                @if($orderSettings?->pickup_enabled)
                    <li>
                        @if($hasValidPickup)
                            <span style="color: #00e5a8;">✓ Самовывоз: адрес и оплата настроены</span>
                        @else
                            <span style="color: #ffb74d;">○ Для самовывоза укажите адрес и включите наличные/терминал</span>
                        @endif
                    </li>
                @endif
                @if($hasValidDineIn)
                    <li>
                        <span style="color: #00e5a8;">✓ Заказ в заведении: гостевой счёт, расчёт при выходе</span>
                    </li>
                @endif
                @if(!$hasValidDelivery && !$hasValidPickup && !$hasValidDineIn)
                    <li><span style="color: #ffb74d;">○ Настройте хотя бы один доступный способ получения заказа</span></li>
                @endif
            </ul>
            @if(!$orderSettings?->canAcceptOrders())
                <div style="margin-top: 8px; color: #ffb74d;">
                    Пока все условия не выполнены, клиент не сможет оформить заказ.
                </div>
            @endif
        </div>

        <button type="submit" class="organization-button" style="margin-top: 16px;">
            Сохранить настройки заказов
        </button>
    </form>

    <div class="organization-order-hint">
        @if($orderSettings?->canAcceptOrders())
            <span style="color: #00e5a8;">● Онлайн-заказы включены</span>
        @else
            <span style="color: #d8b45b;">● Онлайн-заказы пока выключены или настройки не завершены</span>
        @endif
    </div>

    <a
        href="{{ route('organizations.orders.index', $organization) }}"
        class="organization-button"
        style="display: inline-flex; margin-top: 14px; text-decoration: none;"
    >
        📋 Управление заказами
    </a>

    
</div></section>



<section class="organization-tab-panel" data-tab-panel="orders"><div class="organization-section">
<h2 class="organization-section-title">📋 Управление заказами</h2><div class="organization-quick-links">
<a class="organization-quick-link" href="{{ route('organizations.orders.index', $organization) }}"><strong>Все заказы</strong><small>Просмотр заказов, статусов и деталей выполнения</small></a>
</div></div></section>
<section class="organization-tab-panel" data-tab-panel="tv">
<div class="organization-section" id="tv-screens">
    <div class="tv-management-heading">
        <h2 class="organization-section-title">📺 TV-экраны организации</h2>
        <form method="POST" action="{{ route('organizations.tv.screens.store', $organization) }}">
            @csrf
            <button type="submit" class="organization-button" {{ count($tvScreens) >= 20 ? 'disabled' : '' }}>＋ Добавить экран</button>
        </form>
    </div>

    <div class="tv-background-grid">
        @foreach($tvScreens as $screen)
            @php
                $tvScreenConfig = $tvScreensConfig[$screen] ?? [];
                $tvScreenName = $tvScreenConfig['name'] ?? ('TV-экран ' . $screen);
                $tvBackgroundUrl = !empty($tvScreenConfig['background_cid'])
                    ? app(\App\Services\IPFSService::class)->gatewayUrl($tvScreenConfig['background_cid'])
                    : null;
            @endphp

            @if(!$tvBackgroundUrl)
                @foreach(['png', 'jpg', 'jpeg', 'webp'] as $extension)
                    @php
                        $candidatePath = 'images/org/' . $organization->id . '/tv-backgrounds/tv' . $screen . '.' . $extension;
                    @endphp
                    @if(is_file(public_path($candidatePath)))
                        @php
                            $tvBackgroundUrl = asset($candidatePath);
                        @endphp
                        @break
                    @endif
                @endforeach
            @endif

            <div class="tv-background-card">

                <div class="tv-background-card-heading">
                    <strong>{{ $tvScreenName }}</strong>
                    <a href="{{ route('organization.tv', $organization) }}?screen={{ $screen }}" target="_blank" rel="noopener">Предпросмотр ↗</a>
                </div>

                <form method="POST" action="{{ route('organizations.tv.screens.settings', [$organization, $screen]) }}" class="tv-screen-settings">
                    @csrf
                    @method('PATCH')

                    <label for="tv-screen-name-{{ $screen }}">Название экрана</label>
                    <input id="tv-screen-name-{{ $screen }}" type="text" name="screen_name" maxlength="60" value="{{ old('screen_name', $tvScreenName) }}" required>

                    <label for="tv-background-fit-{{ $screen }}">Отображение фона</label>
                    <select id="tv-background-fit-{{ $screen }}" name="background_fit">
                        <option value="cover" @selected(old('background_fit', $tvScreenConfig['background_fit'] ?? 'cover') === 'cover')>Заполнить экран (cover)</option>
                        <option value="contain" @selected(old('background_fit', $tvScreenConfig['background_fit'] ?? 'cover') === 'contain')>Вписать целиком (contain)</option>
                        <option value="stretch" @selected(old('background_fit', $tvScreenConfig['background_fit'] ?? 'cover') === 'stretch')>Растянуть (stretch)</option>
                    </select>

                    <label for="tv-rotation-{{ $screen }}">Автопереключение экранов, секунд</label>
                    <input id="tv-rotation-{{ $screen }}" type="number" name="rotation_seconds" min="0" max="3600" value="{{ old('rotation_seconds', (int) ($tvScreenConfig['rotation_seconds'] ?? 0)) }}">
                    <small>0 — выключено для этого экрана. Для непрерывного переключения задайте интервал на каждом экране.</small>

                    <button type="submit" class="organization-button">Сохранить настройки экрана</button>
                </form>

                @if($tvBackgroundUrl)
                    <img class="tv-background-preview" src="{{ $tvBackgroundUrl }}" alt="Фон TV-экрана {{ $screen }}">
                @else
                    <div class="tv-background-empty">Фон не загружен</div>
                @endif

                <form method="POST" action="{{ route('organizations.tv.background.store', $organization) }}" enctype="multipart/form-data" class="tv-background-form">
                    @csrf
                    <input type="hidden" name="screen" value="{{ $screen }}">
                    <label for="tv-background-{{ $screen }}">Изображение фона</label>
                    <input id="tv-background-{{ $screen }}" type="file" name="background" accept="image/png,image/jpeg,image/webp" required>
                    <small>PNG, JPG или WEBP · до 10 МБ</small>
                    <button type="submit" class="organization-button">Загрузить фон</button>
                </form>

                @if($tvBackgroundUrl)
                    <form method="POST" action="{{ route('organizations.tv.background.delete', $organization) }}" onsubmit="return confirm('Удалить фон TV-экрана {{ $screen }}?')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="screen" value="{{ $screen }}">
                        <button type="submit" class="tv-background-delete">Удалить фон</button>
                    </form>
                @endif

                @if(count($tvScreens) > 1)
                    <form method="POST" action="{{ route('organizations.tv.screens.delete', [$organization, $screen]) }}" onsubmit="return confirm('Удалить TV-экран {{ $screen }}? Разделы меню будут перенесены на первый экран, остальные экраны перенумеруются.')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="expected_screens_count" value="{{ count($tvScreens) }}">
                        <button type="submit" class="tv-screen-delete">Удалить TV-экран</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <div class="organization-order-hint">
        Фон настраивается отдельно для каждого экрана и применяется автоматически. Рекомендуемый формат — горизонтальное изображение 16:9.
        Распределение разделов по экранам настраивается в редакторе меню в параметре «TV экран» каждого раздела.
    </div>

    <a href="{{ route('organizations.menu.index', $organization) }}" class="organization-button" style="display:inline-flex;margin-top:14px;text-decoration:none;">Настроить распределение разделов</a>
</div>
</section>
{{-- =====================================================
         НАЗАД
         ===================================================== --}}

    <a
        href="{{ route('organizations.index', $organization) }}"
        class="organization-back"
    >
        ← {{ __('organizations.back_to_organization') }}
    </a>


<script>
document.addEventListener('DOMContentLoaded', function () {
 const root=document.querySelector('.organization-container');if(!root)return;
 const tabs=Array.from(root.querySelectorAll('[data-organization-tabs] [data-tab]'));
 const panels=Array.from(root.querySelectorAll('[data-tab-panel]'));
 const storageKey='organization-management-tab-{{ $organization->id }}';
 function activateTab(name,persist=true){
  if(!panels.some(panel=>panel.dataset.tabPanel===name))name='overview';
  tabs.forEach(tab=>{const active=tab.dataset.tab===name;tab.classList.toggle('is-active',active);if(active)tab.setAttribute('aria-current','page');else tab.removeAttribute('aria-current');});
  panels.forEach(panel=>{const active=panel.dataset.tabPanel===name;panel.classList.toggle('is-active',active);panel.hidden=!active;});
  if(persist){try{sessionStorage.setItem(storageKey,name);}catch(error){}}
 }
 tabs.forEach(tab=>tab.addEventListener('click',()=>activateTab(tab.dataset.tab)));
 let initialTab='overview';try{initialTab=sessionStorage.getItem(storageKey)||'overview';}catch(error){}
 const hashTab=window.location.hash.replace(/^#/, '');
 if(panels.some(panel=>panel.dataset.tabPanel===hashTab))initialTab=hashTab;
 @if($errors->has('screen_name') || $errors->has('background_fit') || $errors->has('rotation_seconds'))
 initialTab='tv';
 @elseif($errors->has('currency') || $errors->has('pickup_min_minutes') || $errors->has('delivery_enabled') || $errors->has('cash_payment_enabled') || $errors->has('delivery_zones') || $errors->has('delivery_origin_latitude') || $errors->has('delivery_origin_longitude') || $errors->has('working_hours') || $errors->has('timezone') || $errors->has('order_lead_minutes') || $errors->has('time_slot_minutes') || $errors->has('preorders_enabled'))
 initialTab='settings';
 @elseif($errors->has('name') || $errors->has('phone') || $errors->has('role') || $errors->has('user_id'))
 initialTab='team';
 @endif
 activateTab(initialTab,false);
 root.querySelectorAll('form').forEach(form=>form.addEventListener('submit',function(){const active=root.querySelector('[data-tab-panel].is-active');if(active){try{sessionStorage.setItem(storageKey,active.dataset.tabPanel);}catch(error){}}}));
});
</script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const deliveryToggle = document.querySelector('input[name="delivery_enabled"]');
    const zonePanel = document.getElementById('organization-delivery-zones');
    const zoneList = document.getElementById('organization-delivery-zones-list');
    const addZoneButton = document.getElementById('organization-delivery-zone-add');
    const mapElement = document.getElementById('organization-delivery-origin-map');
    if (!deliveryToggle || !zonePanel || !zoneList || !addZoneButton || !mapElement) return;

    const updatePanel = () => {
        zonePanel.hidden = !deliveryToggle.checked;
        if (!zonePanel.hidden && window.deliveryOriginMap) {
            setTimeout(() => window.deliveryOriginMap.invalidateSize(), 150);
        }
    };
    deliveryToggle.addEventListener('change', updatePanel);
    updatePanel();

    const renumberZones = () => {
        [...zoneList.querySelectorAll('[data-zone-row]')].forEach((row, index) => {
            row.querySelector('.organization-delivery-zone-number').textContent = String(index + 1);
            row.querySelectorAll('input').forEach((input) => {
                const field = input.name.match(/\[(max_distance_km|minimum_order_amount)\]$/)?.[1];
                if (field) input.name = 'delivery_zones[' + index + '][' + field + ']';
            });
        });
    };
    addZoneButton.addEventListener('click', () => {
        const index = zoneList.querySelectorAll('[data-zone-row]').length;
        if (index >= 100) return;
        const row = document.createElement('div');
        row.className = 'organization-delivery-zone-row';
        row.setAttribute('data-zone-row', '');
        row.innerHTML = '<span class="organization-delivery-zone-number"></span>' +
            '<label><span>До, км</span><input type="number" name="delivery_zones[' + index + '][max_distance_km]" min="0.01" max="10" step="0.01" required></label>' +
            '<label><span>Минимум корзины</span><input type="number" name="delivery_zones[' + index + '][minimum_order_amount]" value="0.00" min="0" max="9999999999.99" step="0.01" required></label>' +
            '<button type="button" class="organization-delivery-zone-remove" aria-label="Удалить зону" title="Удалить зону">×</button>';
        zoneList.appendChild(row);
        renumberZones();
    });
    zoneList.addEventListener('click', (event) => {
        const button = event.target.closest('.organization-delivery-zone-remove');
        if (!button) return;
        button.closest('[data-zone-row]')?.remove();
        renumberZones();
    });

    if (!window.L) return;
    const latInput = document.getElementById('organization-delivery-origin-latitude');
    const lngInput = document.getElementById('organization-delivery-origin-longitude');
    const addressInput = document.getElementById('organization-pickup-address');
    const status = document.getElementById('organization-delivery-origin-status');
    const findButton = document.getElementById('organization-delivery-find-address');
    const normalizeLongitude = value => {
        const longitude = Number(value);
        if (!Number.isFinite(longitude)) return NaN;
        return ((longitude + 180) % 360 + 360) % 360 - 180;
    };
    const hasPoint = latInput.value !== '' && lngInput.value !== ''
        && Number.isFinite(Number(latInput.value))
        && Number(latInput.value) >= -90 && Number(latInput.value) <= 90;
    const initialLat = hasPoint ? Number(latInput.value) : 0;
    const initialLng = hasPoint ? normalizeLongitude(lngInput.value) : 0;
    if (hasPoint) lngInput.value = initialLng.toFixed(7);
    const map = L.map(mapElement).setView([initialLat, initialLng], hasPoint ? 16 : 2);
    window.deliveryOriginMap = map;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    let marker = null;
    const setPoint = (lat, lng) => {
        lat = Number(lat);
        lng = normalizeLongitude(lng);
        if (!Number.isFinite(lat) || lat < -90 || lat > 90 || !Number.isFinite(lng)) {
            status.textContent = 'Не удалось определить корректные координаты. Выберите точку на карте.';
            return;
        }
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
        if (marker) marker.setLatLng([lat, lng]);
        else marker = L.marker([lat, lng], {draggable: true}).addTo(map);
        marker.off('dragend');
        marker.on('dragend', () => {
            const point = marker.getLatLng();
            const normalizedLng = normalizeLongitude(point.lng);
            latInput.value = point.lat.toFixed(7);
            lngInput.value = normalizedLng.toFixed(7);
            marker.setLatLng([point.lat, normalizedLng]);
            status.textContent = 'Центр доставки выбран: ' + point.lat.toFixed(5) + ', ' + normalizedLng.toFixed(5);
        });
        status.textContent = 'Центр доставки выбран: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
    };
    if (hasPoint) setPoint(Number(latInput.value), Number(lngInput.value));
    map.on('click', event => setPoint(event.latlng.lat, event.latlng.lng));
    addressInput?.addEventListener('input', () => {
        latInput.value = '';
        lngInput.value = '';
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
        status.textContent = 'Адрес изменён. Найдите его на карте и подтвердите точку.';
    });
    findButton?.addEventListener('click', async () => {
        const query = addressInput?.value.trim();
        if (!query) { status.textContent = 'Сначала укажите адрес самовывоза.'; return; }
        status.textContent = 'Ищем адрес…';
        try {
            const response = await fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(query), {headers: {'Accept-Language': 'ru'}});
            const results = await response.json();
            if (!results.length) { status.textContent = 'Адрес не найден. Выберите точку вручную на карте.'; return; }
            const lat = Number(results[0].lat), lng = Number(results[0].lon);
            map.setView([lat, lng], 17);
            setPoint(lat, lng);
        } catch (error) {
            status.textContent = 'Не удалось найти адрес. Выберите точку вручную на карте.';
        }
    });
    setTimeout(() => map.invalidateSize(), 250);
})();

</script>

@endsection