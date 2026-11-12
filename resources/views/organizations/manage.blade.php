@extends('template')

@section('title_page')
    {{ __('organizations.management') }} — {{ $organization->name }}
@endsection

@section('main')

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

</style>


<div class="organization-container">

    {{-- =====================================================
         ЗАГОЛОВОК ОРГАНИЗАЦИИ
         ===================================================== --}}

    <div class="organization-header">

    <div class="organization-header-main">

        <div class="organization-management-title">
            {{ __('organizations.management') }}
        </div>

        <div class="organization-name">
            {{ $organization->name }}
        </div>

    </div>


    <div style="
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
        justify-content:flex-end;
    ">

        


        <div class="organization-header-status">

            <span class="organization-status-indicator"></span>

            <span>
                {{ __('organizations.status') }}:
                {{ __('organizations.statuses.' . $organization->status) }}
            </span>

        </div>

    </div>

</div>


    {{-- =====================================================
         УВЕДОМЛЕНИЯ
         ===================================================== --}}

    @if(session('success'))

        <div class="organization-alert success">
            {{ session('success') }}
        </div>

    @endif


    @if(session('error'))

        <div class="organization-alert error">
            {{ session('error') }}
        </div>

    @endif


    @if($errors->any())

        <div class="organization-alert error">

            @foreach($errors->all() as $error)

                <div>
                    {{ $error }}
                </div>

            @endforeach

        </div>

    @endif


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

        @else

            <div class="organization-empty">
                {{ __('organizations.no_available_users') }}
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

{{-- =====================================================
     НАСТРОЙКИ ОНЛАЙН-ЗАКАЗОВ
     ===================================================== --}}
<style>
.organization-order-settings {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
    gap: 14px;
    margin-top: 16px;
}
.organization-order-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    color: #d8dce8;
}
.organization-order-input {
    box-sizing: border-box;
    width: 100%;
    min-height: 42px;
    padding: 9px 12px;
    color: #fff;
    background: #090c16;
    border: 1px solid rgba(0, 229, 212, .45);
    border-radius: 9px;
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
    margin-top: 14px;
    color: #9da7b8;
    font-size: .88rem;
    line-height: 1.5;
}
.organization-order-error {
    color: #ff8585;
    font-size: .85rem;
}
</style>

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
            <label class="organization-order-field">
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

            <label class="organization-order-field">
                Стоимость доставки
                <input
                    type="number"
                    name="delivery_fee"
                    class="organization-order-input"
                    value="{{ old('delivery_fee', $orderSettings?->delivery_fee ?? '0.00') }}"
                    min="0"
                    max="9999999999.99"
                    step="0.01"
                    required
                >
                @error('delivery_fee')
                    <span class="organization-order-error">{{ $message }}</span>
                @enderror
            </label>

            <label class="organization-order-field">
                Минимальная сумма заказа
                <input
                    type="number"
                    name="minimum_order"
                    class="organization-order-input"
                    value="{{ old('minimum_order', $orderSettings?->minimum_order) }}"
                    min="0"
                    max="9999999999.99"
                    step="0.01"
                    placeholder="Не ограничено"
                >
                @error('minimum_order')
                    <span class="organization-order-error">{{ $message }}</span>
                @enderror
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
        </div>

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
            Для включения заказов укажите валюту, выберите хотя бы один способ получения
            и один способ оплаты. Онлайн-оплата пока является настройкой доступности:
            платёжный провайдер и серверное подтверждение платежей будут подключены отдельно.
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
        href="{{ route('customer.catalog', $organization) }}"
        class="organization-button"
        style="display: inline-flex; margin-top: 14px; text-decoration: none;"
        target="_blank"
        rel="noopener"
    >
        Открыть клиентский каталог
    </a>
</div>

{{-- =====================================================
     УПРАВЛЕНИЕ МЕНЮ
     ===================================================== --}}

<div class="organization-section">

    <h2 class="organization-section-title">
        Управление меню
    </h2>

    <a
        href="{{ route('organizations.menu.index', $organization) }}"
        class="organization-button"
        style="display: inline-flex; align-items: center; justify-content: center; text-decoration: none;"
    >
        🍽 Управление меню
    </a>

</div>

    {{-- =====================================================
         НАЗАД
         ===================================================== --}}

    <a
        href="{{ route('organizations.index', $organization) }}"
        class="organization-back"
    >
        ← {{ __('organizations.back_to_organization') }}
    </a>

</div>

@endsection