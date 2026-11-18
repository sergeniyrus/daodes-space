@extends('template')

@section('title_page')
    Управление меню — {{ $organization->name }}
@endsection

@section('main')

<style>

.menu-admin-container {
    max-width: 1100px;
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
   HEADER
   ========================================================= */

.menu-admin-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    padding: 5px 5px 18px;

    margin-bottom: 20px;

    border-bottom: 1px solid rgba(255, 215, 0, 0.20);
}

.menu-admin-header-title {
    min-width: 0;
}

.menu-admin-title-small {
    color: #888;

    font-size: 0.85rem;

    margin-bottom: 5px;
}

.menu-admin-title {
    color: gold;

    font-size: 1.7rem;
    font-weight: 700;

    text-shadow:
        0 0 10px rgba(255, 215, 0, 0.25);
}

.menu-admin-header-actions {
    display: flex;
    gap: 10px;

    flex-shrink: 0;
}


/* =========================================================
   ALERT
   ========================================================= */

.menu-admin-alert {
    padding: 11px 15px;

    margin-bottom: 15px;

    border-radius: 12px;

    text-align: center;
}

.menu-admin-alert.success {
    color: #4caf50;

    border: 1px solid #4caf50;

    background: rgba(76, 175, 80, 0.08);
}

.menu-admin-alert.error {
    color: #ff6b6b;

    border: 1px solid #ff6b6b;

    background: rgba(255, 107, 107, 0.08);
}


/* =========================================================
   BUTTONS
   ========================================================= */

.menu-admin-button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-height: 42px;

    padding: 0 17px;

    background: #0b0c18;

    color: gold;

    border: 1px solid gold;

    border-radius: 21px;

    text-decoration: none;

    font-weight: 600;

    cursor: pointer;

    transition: 0.2s;
}

.menu-admin-button:hover {
    background: gold;

    color: #0b0c18;

    box-shadow:
        0 0 12px rgba(255, 215, 0, 0.35);
}

.menu-admin-button.cyan {
    color: #00e5ff;

    border-color: #00e5ff;
}

.menu-admin-button.cyan:hover {
    background: #00e5ff;

    color: #0b0c18;

    box-shadow:
        0 0 12px rgba(0, 229, 255, 0.35);
}

.menu-admin-button.danger {
    color: #ff6b6b;

    border-color: #ff6b6b;
}

.menu-admin-button.danger:hover {
    background: #ff5252;

    color: #fff;
}


/* =========================================================
   SECTION
   ========================================================= */

.menu-section {
    margin-bottom: 18px;

    padding: 18px;

    background:
        linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.025),
            rgba(255, 255, 255, 0.01)
        );

    border: 1px solid rgba(255, 215, 0, 0.25);

    border-radius: 16px;

    transition: border-color 0.3s;
}

.menu-section:last-child {
    margin-bottom: 0;
}

.menu-section.open {
    border-color: rgba(0, 229, 255, 0.45);
}


/* =========================================================
   SECTION HEADER
   ========================================================= */

.menu-section-header {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 15px;

    margin-bottom: 15px;

    cursor: pointer;

    user-select: none;

    transition: 0.2s;
}

.menu-section.collapsed .menu-section-header {
    margin-bottom: 0;
}

.menu-section-header:hover .menu-section-title {
    text-shadow: 0 0 12px rgba(255, 215, 0, 0.45);
}

/* Название слева, мета справа — одинаково в обоих состояниях */
.menu-section-title-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.menu-section-title {
    color: gold;

    font-size: 1.25rem;
    font-weight: 700;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.menu-section-meta {
    color: #888;

    font-size: 0.8rem;

    white-space: nowrap;

    margin-left: auto;
}

.menu-section-actions {
    display: flex;

    gap: 8px;

    flex-wrap: wrap;

    justify-content: flex-end;

    flex-shrink: 0;
}

.menu-section-quick-actions,
.menu-item-quick-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.menu-quick-form {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin: 0;
}

.menu-section-settings-form {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 9px;
    padding: 10px;
    background: rgba(0, 229, 255, .045);
    border: 1px solid rgba(0, 229, 255, .2);
    border-radius: 12px;
}

.menu-section-setting {
    display: flex;
    flex-direction: column;
    gap: 5px;
    color: #aaa;
    font-size: .75rem;
}

.menu-section-select {
    min-height: 38px;
    padding: 4px 9px;
    color: #fff;
    background: #171927;
    border: 1px solid rgba(0, 229, 255, .55);
    border-radius: 9px;
}

.menu-section-text-input {
    width: 190px;
    min-height: 38px;
    box-sizing: border-box;
    padding: 4px 10px;
    color: #fff;
    background: #171927;
    border: 1px solid rgba(0, 229, 255, .55);
    border-radius: 9px;
}

.menu-section-text-input:focus,
.menu-section-select:focus,
.menu-sort-input:focus {
    outline: none;
    border-color: gold;
    box-shadow: 0 0 8px rgba(255, 215, 0, .2);
}

.menu-section-check {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 38px;
    color: #ddd;
    font-size: .82rem;
    white-space: nowrap;
}

.menu-section-check input {
    accent-color: #00e5ff;
    width: 17px;
    height: 17px;
}

.menu-sort-input {
    width: 64px;
    min-height: 38px;
    box-sizing: border-box;
    padding: 4px 7px;
    color: #fff;
    background: #171927;
    border: 1px solid rgba(0, 229, 255, 0.55);
    border-radius: 9px;
    text-align: center;
}

.menu-quick-button {
    min-height: 38px;
    padding: 0 11px;
    border-radius: 18px;
    font-size: 0.82rem;
}

.menu-item.inactive {
    opacity: 0.58;
    border-style: dashed;
}

.menu-section.inactive-section {
    border-color: rgba(255, 183, 77, 0.55);
}

.menu-section.inactive-section .menu-section-title {
    color: #ffb74d;
}


/* =========================================================
   SECTION BODY (аккордеон)
   ========================================================= */

.menu-section-body {
    overflow: hidden;

    max-height: 5000px;

    opacity: 1;

    transition:
        max-height 0.45s ease,
        opacity 0.35s ease,
        margin-top 0.35s ease;

    margin-top: 15px;
}

.menu-section.collapsed .menu-section-body {
    max-height: 0;
    opacity: 0;
    margin-top: 0;
}


/* =========================================================
   ITEM
   ========================================================= */

.menu-item {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 15px;

    padding: 13px 14px;

    margin-bottom: 8px;

    background: #2B2C2E;

    border: 1px solid #444;

    border-radius: 12px;

    transition: 0.2s;
}

.menu-item:last-child {
    margin-bottom: 0;
}

.menu-item:hover {
    border-color: #00e5ff;

    box-shadow:
        0 4px 15px rgba(0, 0, 0, 0.25);
}

.menu-item-main {
    min-width: 0;

    flex: 1;
}

.menu-item-name {
    color: #fff;

    font-weight: 600;

    margin-bottom: 5px;
}

.menu-item-description {
    color: #999;

    font-size: 0.82rem;

    margin-bottom: 6px;
}

.menu-item-data {
    display: flex;

    flex-wrap: wrap;

    gap: 7px;
}

.menu-item-badge {
    display: inline-block;

    padding: 3px 8px;

    border-radius: 10px;

    border: 1px solid #555;

    color: #bbb;

    font-size: 0.75rem;
}

.menu-item-badge.price {
    color: gold;

    border-color: gold;
}

.menu-item-badge.active {
    color: #4caf50;

    border-color: #4caf50;
}

.menu-item-badge.inactive {
    color: #ffb74d;

    border-color: #ffb74d;
}

.menu-item-actions {
    display: flex;

    flex-direction: column;

    gap: 7px;

    align-items: flex-end;

    flex-shrink: 0;
}

.menu-item-meta {
    display: flex;

    gap: 7px;
}

.menu-item-buttons {
    display: flex;

    gap: 7px;
}


/* =========================================================
   EMPTY
   ========================================================= */

.menu-empty {
    padding: 20px;

    text-align: center;

    color: #888;

    border: 1px dashed #444;

    border-radius: 12px;
}


/* =========================================================
   FOOTER
   ========================================================= */

.menu-admin-footer {
    margin-top: 20px;

    text-align: center;
}

.menu-admin-back {
    color: #00e5ff;

    text-decoration: none;

    font-size: 0.9rem;
}

.menu-admin-back:hover {
    color: gold;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 750px) {

    .menu-admin-container {
        margin: 20px 10px 35px;

        padding: 15px;

        border-radius: 17px;
    }

    .menu-admin-header {
        display: block;

        text-align: center;
    }

    .menu-admin-header-actions {
        justify-content: center;

        margin-top: 15px;

        flex-wrap: wrap;
    }

    .menu-section-header {
        display: block;
    }

    .menu-section-title-wrapper {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .menu-section-meta {
        margin-left: 0;
    }

    .menu-section-actions {
        display: flex;
        margin-top: 12px;
        justify-content: flex-start;
    }

    .menu-section-settings-form {
        align-items: stretch;
        width: 100%;
    }

    .menu-section-setting {
        flex: 1 1 90px;
    }

    .menu-section-text-input {
        width: 100%;
    }

    .menu-section-settings-form .menu-admin-button {
        flex: 1 1 100%;
    }

    .menu-item {
        display: block;
    }

    .menu-item-name {
        font-size: 1.1rem;
        text-align: center;
    }

    .menu-item-description {
        font-size: 0.95rem;
        text-align: center;
    }

    .menu-item-data {
        justify-content: center;
    }

    .menu-item-badge {
        font-size: 0.85rem;
        padding: 4px 10px;
    }

    .menu-item-actions {
        margin-top: 12px;
        align-items: stretch;
    }

    .menu-item-meta {
        justify-content: center;
    }

    .menu-item-buttons {
        justify-content: center;
    }
}

@media (max-width: 500px) {

    .menu-admin-title {
        font-size: 1.35rem;
    }

    .menu-admin-header-actions,
    .menu-section-actions,
    .menu-item-buttons {
        flex-direction: column;
    }

    .menu-admin-button {
        width: 100%;

        box-sizing: border-box;
    }
}


/* =========================================================
   DAODES: FULL-WIDTH MENU WORKSPACE
   ========================================================= */

.menu-admin-container {
    width: calc(100% - 32px);
    max-width: none;
    box-sizing: border-box;
    margin: 22px auto 42px;
    padding: clamp(14px, 2vw, 28px);
    overflow: visible;
    background:
        radial-gradient(ellipse at 92% 0%, rgba(255, 215, 0, .10), transparent 34%),
        radial-gradient(ellipse at 0% 100%, rgba(0, 229, 212, .09), transparent 38%),
        linear-gradient(145deg, #080d18 0%, #101827 48%, #080d18 100%);
    border: 1px solid rgba(0, 229, 212, .48);
    border-radius: 20px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, .48), inset 0 1px 0 rgba(255, 215, 0, .14);
}

.menu-admin-header {
    border-bottom: 1px solid rgba(0, 229, 212, .28);
}

.menu-admin-title {
    color: #f5cf67;
    letter-spacing: .025em;
    text-shadow: 0 0 16px rgba(245, 207, 103, .22);
}

.menu-admin-title-small {
    color: #8ba5b5;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.menu-section {
    width: 100%;
    box-sizing: border-box;
    padding: clamp(12px, 1.5vw, 20px);
    background:
        linear-gradient(145deg, rgba(20, 34, 49, .94), rgba(9, 16, 29, .96));
    border: 1px solid rgba(245, 207, 103, .24);
    border-radius: 15px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .025);
}

.menu-section.open {
    border-color: rgba(0, 229, 212, .62);
    box-shadow: 0 0 18px rgba(0, 229, 212, .07);
}

.menu-section-header {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 13px;
}

.menu-section-title-wrapper {
    width: 100%;
}

.menu-section-title {
    color: #f5cf67;
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
}

.menu-section-actions {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    justify-content: flex-start;
    align-items: stretch;
}

.menu-section-quick-actions {
    width: 100%;
    min-width: 0;
}

.menu-section-settings-form {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    align-items: end;
    background: rgba(0, 229, 212, .045);
    border-color: rgba(0, 229, 212, .24);
}

.menu-section-setting {
    flex: 0 1 auto;
}

.menu-section-name-setting {
    flex: 1 1 220px;
    min-width: 160px;
}

.menu-section-text-input {
    width: 100%;
    min-width: 0;
}

.menu-section-select,
.menu-section-text-input,
.menu-sort-input {
    background: #0b1421;
    border-color: rgba(0, 229, 212, .48);
}

.menu-section-check {
    color: #d5e1e8;
}

.menu-item {
    background: linear-gradient(120deg, rgba(19, 34, 49, .98), rgba(12, 22, 36, .98));
    border-color: rgba(0, 229, 212, .20);
}

.menu-item:hover {
    border-color: rgba(0, 229, 212, .72);
    box-shadow: 0 5px 18px rgba(0, 0, 0, .28), 0 0 12px rgba(0, 229, 212, .07);
}

.menu-item-name {
    color: #f3f6f8;
}

.menu-item-description {
    color: #9db0bd;
}

.menu-item-badge.price {
    color: #f5cf67;
    border-color: rgba(245, 207, 103, .72);
}

.menu-admin-button {
    background: linear-gradient(145deg, #111d2b, #0a111d);
    border-color: rgba(245, 207, 103, .75);
    color: #f5cf67;
}

.menu-admin-button.cyan {
    color: #00e5d4;
    border-color: rgba(0, 229, 212, .78);
}

.menu-admin-button.danger {
    color: #ff8585;
    border-color: rgba(255, 107, 107, .72);
}

.menu-empty {
    color: #a9bac5;
    border-color: rgba(0, 229, 212, .32);
    background: rgba(0, 229, 212, .035);
}

@media (max-width: 750px) {
    .menu-admin-container {
        width: calc(100% - 16px);
        margin: 12px auto 28px;
        padding: 12px;
        border-radius: 15px;
    }

    .menu-section-header {
        display: flex;
    }

    .menu-section-actions,
    .menu-section-quick-actions {
        width: 100%;
    }

    .menu-section-settings-form {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        padding: 10px;
    }

    .menu-section-name-setting,
    .menu-section-settings-form > button {
        grid-column: 1 / -1;
    }

    .menu-section-setting,
    .menu-section-text-input,
    .menu-section-select,
    .menu-sort-input {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .menu-section-check {
        white-space: normal;
    }

    .menu-item {
        width: 100%;
        box-sizing: border-box;
    }
}

@media (max-width: 420px) {
    .menu-admin-container {
        width: calc(100% - 10px);
        padding: 9px;
    }

    .menu-section {
        padding: 10px;
    }

    .menu-section-actions,
    .menu-section-quick-actions,
    .menu-section-settings-form {
        min-width: 0;
    }

    .menu-section-settings-form .menu-admin-button {
        width: 100%;
    }
}


/* Desktop: one compact horizontal row per menu section. */
@media (min-width: 1100px) {
    .menu-section-header {
        display: grid;
        grid-template-columns: minmax(135px, .72fr) minmax(0, 4fr);
        align-items: center;
        gap: 14px;
        margin-bottom: 0;
    }

    .menu-section-title-wrapper {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        gap: 5px;
        min-width: 0;
    }

    .menu-section-title {
        width: 100%;
        font-size: 1.08rem;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .menu-section-meta {
        margin-left: 0;
        font-size: .72rem;
    }

    .menu-section-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        gap: 6px;
        min-width: 0;
        width: 100%;
    }

    .menu-section-quick-actions {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        gap: 6px;
        min-width: 0;
        flex: 1 1 auto;
    }

    .menu-section-settings-form {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        gap: 6px;
        min-width: 0;
        flex: 1 1 auto;
        padding: 6px;
    }

    .menu-section-setting {
        flex: 0 0 auto;
        gap: 3px;
        font-size: .66rem;
    }

    .menu-section-name-setting {
        flex: 1 1 130px;
        min-width: 95px;
    }

    .menu-section-text-input {
        width: 100%;
        min-width: 0;
        min-height: 34px;
        padding: 3px 7px;
        font-size: .78rem;
    }

    .menu-sort-input,
    .menu-section-select {
        min-height: 34px;
        height: 34px;
        padding: 3px 6px;
        font-size: .76rem;
    }

    .menu-sort-input {
        width: 48px;
    }

    .menu-section-select {
        width: 66px;
    }

    .menu-section-check {
        min-height: 34px;
        gap: 3px;
        font-size: .7rem;
    }

    .menu-section-check input {
        width: 14px;
        height: 14px;
        margin: 0;
    }

    .menu-quick-button,
    .menu-section-actions > .menu-admin-button,
    .menu-section-actions > form > .menu-admin-button {
        min-height: 34px;
        padding: 0 8px;
        font-size: .72rem;
        white-space: nowrap;
    }

    .menu-section-actions > .menu-admin-button,
    .menu-section-actions > form {
        flex: 0 0 auto;
    }
}

</style>


<div class="menu-admin-container">

    {{-- =====================================================
         HEADER
         ===================================================== --}}

    <div class="menu-admin-header">

        <div class="menu-admin-header-title">

            <div class="menu-admin-title-small">
                Управление меню
            </div>

            <div class="menu-admin-title">
                {{ $organization->name }}
            </div>

        </div>

        <div class="menu-admin-header-actions">

            <a
                href="{{ route('organizations.menu.sections.create', $organization) }}"
                class="menu-admin-button"
            >
                + Добавить раздел
            </a>

        </div>

    </div>


    {{-- =====================================================
         УВЕДОМЛЕНИЯ
         ===================================================== --}}

    @if(session('success'))

        <div class="menu-admin-alert success">
            {{ session('success') }}
        </div>

    @endif


    @if(session('error'))

        <div class="menu-admin-alert error">
            {{ session('error') }}
        </div>

    @endif


    @if($errors->any())

        <div class="menu-admin-alert error">

            @foreach($errors->all() as $error)

                <div>
                    {{ $error }}
                </div>

            @endforeach

        </div>

    @endif


    {{-- =====================================================
         РАЗДЕЛЫ МЕНЮ
         ===================================================== --}}

    @forelse($sections as $section)

        <div
            class="menu-section collapsed {{ !$section->is_active ? 'inactive-section' : '' }}"
            data-section
        >

            <div class="menu-section-header" data-section-toggle>

                <div class="menu-section-title-wrapper">

                    <div class="menu-section-title">
                        {{ $section->name }}
                    </div>

                    <div class="menu-section-meta">
                        {{ $section->is_active ? 'активен' : 'выключен' }}
                        ·
                        {{ $section->items->count() }}
                        {{ $section->items->count() == 1 ? 'блюдо' : 'блюд' }}
                    </div>

                </div>


                <div class="menu-section-actions">

                    <div class="menu-section-quick-actions" data-stop-propagation>
                        <form
                            method="POST"
                            action="{{ route('organizations.menu.sections.quick_settings', [
                                'organization' => $organization,
                                'section' => $section,
                            ]) }}"
                            class="menu-section-settings-form"
                        >
                            @csrf
                            @method('PATCH')

                            <label class="menu-section-setting menu-section-name-setting">
                                <span>Название раздела</span>
                                <input
                                    class="menu-section-text-input"
                                    type="text"
                                    name="name"
                                    maxlength="255"
                                    value="{{ $section->name }}"
                                    required
                                    aria-label="Название раздела"
                                >
                            </label>

                            <label class="menu-section-setting">
                                <span>Порядок</span>
                                <input
                                    class="menu-sort-input"
                                    type="number"
                                    name="sort_order"
                                    min="0"
                                    value="{{ $section->sort_order }}"
                                    aria-label="Порядок раздела"
                                >
                            </label>

                            <label class="menu-section-setting">
                                <span>TV экран</span>
                                <select name="tv_screen" class="menu-section-select" aria-label="TV экран">
                                    <option value="1" {{ (int) $section->tv_screen === 1 ? 'selected' : '' }}>TV 1</option>
                                    <option value="2" {{ (int) $section->tv_screen === 2 ? 'selected' : '' }}>TV 2</option>
                                    <option value="3" {{ (int) $section->tv_screen === 3 ? 'selected' : '' }}>TV 3</option>
                                </select>
                            </label>

                            <label class="menu-section-check">
                                <input type="hidden" name="delivery_enabled" value="0">
                                <input type="checkbox" name="delivery_enabled" value="1" {{ $section->delivery_enabled ? 'checked' : '' }}>
                                <span>Доставка</span>
                            </label>

                            <label class="menu-section-check">
                                <input type="hidden" name="pickup_enabled" value="0">
                                <input type="checkbox" name="pickup_enabled" value="1" {{ $section->pickup_enabled ? 'checked' : '' }}>
                                <span>Самовывоз</span>
                            </label>

                            <button type="submit" class="menu-admin-button menu-quick-button">Сохранить настройки</button>
                        </form>

                        <form
                            method="POST"
                            action="{{ route('organizations.menu.sections.toggle', [
                                'organization' => $organization,
                                'section' => $section,
                            ]) }}"
                            class="menu-quick-form"
                        >
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                class="menu-admin-button menu-quick-button {{ $section->is_active ? 'danger' : 'cyan' }}"
                                title="{{ $section->is_active ? 'Выключить раздел' : 'Включить раздел' }}"
                            >
                                {{ $section->is_active ? 'Выключить раздел' : 'Включить раздел' }}
                            </button>
                        </form>
                    </div>

                    <a
                        href="{{ route('organizations.menu.items.create', [
                            'organization' => $organization,
                            'section' => $section,
                        ]) }}"
                        class="menu-admin-button cyan"
                        data-stop-propagation
                    >
                        + Блюдо
                    </a>

                    <a
                        href="{{ route('organizations.menu.sections.edit', [
                            'organization' => $organization,
                            'section' => $section,
                        ]) }}"
                        class="menu-admin-button"
                        data-stop-propagation
                    >
                        Изменить
                    </a>

                    <form
                        method="POST"
                        action="{{ route('organizations.menu.sections.destroy', [
                            'organization' => $organization,
                            'section' => $section,
                        ]) }}"
                        onsubmit="return confirm('Удалить раздел и все его блюда?');"
                        data-stop-propagation
                    >

                        @csrf

                        @method('DELETE')

                        <button
                            type="submit"
                            class="menu-admin-button danger"
                        >
                            Удалить
                        </button>

                    </form>

                </div>

            </div>


            {{-- =================================================
                 БЛЮДА
                 ================================================= --}}

            <div class="menu-section-body">

                @forelse($section->items as $item)

                    <div class="menu-item {{ !$item->is_active ? 'inactive' : '' }}">

                        <div class="menu-item-main">

                            <div class="menu-item-name">
                                {{ $item->name }}
                            </div>

                            @if($item->description)

                                <div class="menu-item-description">
                                    {{ $item->description }}
                                </div>

                            @endif

                            <div class="menu-item-data">

                                @if($item->weight !== null)

                                    <span class="menu-item-badge">
                                        {{ fmod($item->weight, 1) == 0 ? (int)$item->weight : $item->weight }}
                                        {{ $item->unit }}
                                    </span>

                                @endif

                                <span class="menu-item-badge price">
                                    {{ fmod($item->price, 1) == 0 ? (int)$item->price : $item->price }} ₽
                                </span>

                            </div>

                        </div>


                        <div class="menu-item-actions">

                            <div class="menu-item-meta">

                                <span class="menu-item-badge">
                                    порядок: {{ $item->sort_order }}
                                </span>

                                @if($item->is_active)

                                    <span class="menu-item-badge active">
                                        активно
                                    </span>

                                @else

                                    <span class="menu-item-badge inactive">
                                        выключено
                                    </span>

                                @endif

                            </div>

                            <div class="menu-item-buttons">

                                <div class="menu-item-quick-actions">
                                    <form
                                        method="POST"
                                        action="{{ route('organizations.menu.items.sort', [
                                            'organization' => $organization,
                                            'section' => $section,
                                            'item' => $item,
                                        ]) }}"
                                        class="menu-quick-form"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <input
                                            class="menu-sort-input"
                                            type="number"
                                            name="sort_order"
                                            min="0"
                                            value="{{ $item->sort_order }}"
                                            aria-label="Порядок блюда"
                                        >
                                        <button type="submit" class="menu-admin-button menu-quick-button">№</button>
                                    </form>

                                    <form
                                        method="POST"
                                        action="{{ route('organizations.menu.items.toggle', [
                                            'organization' => $organization,
                                            'section' => $section,
                                            'item' => $item,
                                        ]) }}"
                                        class="menu-quick-form"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="menu-admin-button menu-quick-button {{ $item->is_active ? 'danger' : 'cyan' }}"
                                            title="{{ $item->is_active ? 'Выключить блюдо' : 'Включить блюдо' }}"
                                        >
                                            {{ $item->is_active ? 'Выключить' : 'Включить' }}
                                        </button>
                                    </form>
                                </div>

                                <a
                                    href="{{ route('organizations.menu.items.edit', [
                                        'organization' => $organization,
                                        'section' => $section,
                                        'item' => $item,
                                    ]) }}"
                                    class="menu-admin-button"
                                >
                                    Изменить
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('organizations.menu.items.destroy', [
                                        'organization' => $organization,
                                        'section' => $section,
                                        'item' => $item,
                                    ]) }}"
                                    onsubmit="return confirm('Удалить это блюдо?');"
                                >

                                    @csrf

                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="menu-admin-button danger"
                                    >
                                        Удалить
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                @empty

                    <div class="menu-empty">
                        В этом разделе пока нет блюд.
                    </div>

                @endforelse

            </div>

        </div>

    @empty

        <div class="menu-empty">
            Меню пока пустое.
            Создайте первый раздел.
        </div>

    @endforelse


    {{-- =====================================================
         НАЗАД
         ===================================================== --}}

    <div class="menu-admin-footer">

        <a
            href="{{ route('organizations.manage', $organization) }}"
            class="menu-admin-back"
        >
            ← Вернуться к управлению организацией
        </a>

    </div>

</div>


<script>
(function () {
    const sections = document.querySelectorAll('[data-section]');

    sections.forEach(section => {
        const header = section.querySelector('[data-section-toggle]');
        if (!header) return;

        header.addEventListener('click', function (e) {
            if (e.target.closest('[data-stop-propagation]')) {
                return;
            }

            const isCollapsed = section.classList.contains('collapsed');

            sections.forEach(other => {
                if (other !== section) {
                    other.classList.remove('open');
                    other.classList.add('collapsed');
                }
            });

            if (isCollapsed) {
                section.classList.remove('collapsed');
                section.classList.add('open');
            } else {
                section.classList.remove('open');
                section.classList.add('collapsed');
            }
        });
    });
})();
</script>

@endsection