@extends('organizations.layouts.management')

@section('title_page')
    Редактирование раздела — {{ $section->name }}
@endsection

@section('management-content')
@php
    $menuRoutePrefix = auth('employee')->check()
        ? 'employee.organization.menu.'
        : 'organizations.menu.';
@endphp


<style>

.menu-form-container {
    max-width: 700px;
    margin: 40px auto;
    padding: 25px;

    background:
        linear-gradient(
            145deg,
            #0b0c18,
            #111323,
            #0b0c18
        );

    border: 1px solid rgba(255, 215, 0, 0.55);

    border-radius: 20px;

    color: #fff;
}

.menu-form-title {
    color: gold;

    font-size: 1.5rem;

    margin-bottom: 20px;

    text-align: center;
}

.menu-form-group {
    margin-bottom: 17px;
}

.menu-form-label {
    display: block;

    margin-bottom: 7px;

    color: #aaa;

    font-size: 0.9rem;
}

.menu-form-input {
    width: 100%;

    box-sizing: border-box;

    min-height: 45px;

    padding: 0 14px;

    background: #1a1a1a;

    color: #fff;

    border: 1px solid #555;

    border-radius: 10px;

    outline: none;
}

.menu-form-input:focus {
    border-color: gold;

    box-shadow:
        0 0 8px rgba(255, 215, 0, 0.25);
}

.menu-form-checkbox {
    display: flex;

    align-items: center;

    gap: 8px;

    color: #ccc;
}

.menu-form-actions {
    display: flex;

    gap: 10px;

    justify-content: center;

    margin-top: 25px;
}

.menu-form-button {
    min-height: 43px;

    padding: 0 20px;

    background: #0b0c18;

    color: gold;

    border: 1px solid gold;

    border-radius: 22px;

    cursor: pointer;

    font-weight: 600;
}

.menu-form-button:hover {
    background: gold;

    color: #0b0c18;
}

.menu-form-back {
    display: inline-flex;

    align-items: center;

    padding: 0 20px;

    color: #00e5ff;

    border: 1px solid #00e5ff;

    border-radius: 22px;

    text-decoration: none;
}

</style>
<style>
.menu-form-container{width:100%;max-width:760px;box-sizing:border-box;margin:0 auto;padding:0;background:transparent;border:0;border-radius:0;color:#fff}
</style>


<div class="menu-form-container">

    <div class="menu-form-title">
        Редактирование раздела
    </div>

    <form
        method="POST"
        action="{{ route($menuRoutePrefix . 'sections.update', [
            'organization' => $organization,
            'section' => $section,
        ]) }}"
    >

        @csrf
        @method('PUT')


        <div class="menu-form-group">

            <label class="menu-form-label">
                Название раздела
            </label>

            <input
                type="text"
                name="name"
                class="menu-form-input"
                value="{{ old('name', $section->name) }}"
                required
                maxlength="255"
            >

        </div>


        <div class="menu-form-group">

            <label class="menu-form-label">
                Порядок
            </label>

            <input
                type="number"
                name="sort_order"
                class="menu-form-input"
                value="{{ old('sort_order', $section->sort_order) }}"
                min="0"
            >

        </div>


        <div class="menu-form-group">

            <label class="menu-form-checkbox">

                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', $section->is_active))
                >

                Раздел активен

            </label>

        </div>


        <div class="menu-form-group">
            @if($canManageTv)
                <label class="menu-form-label" for="tv_screen">Экран TV</label>
                <select id="tv_screen" name="tv_screen" class="menu-form-input" required>
                    @foreach($tvScreens as $screen)
                        <option value="{{ $screen }}" @selected(old('tv_screen', $section->tv_screen ?? 1) == $screen)>Экран {{ $screen }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="menu-form-group">
            <label class="menu-form-checkbox">
                <input type="hidden" name="delivery_enabled" value="0">
                <input type="checkbox" name="delivery_enabled" value="1" @checked(old('delivery_enabled', $section->delivery_enabled ?? true))>
                Доставка разрешена для всех блюд раздела
            </label>
        </div>

        <div class="menu-form-group">
            <label class="menu-form-checkbox">
                <input type="hidden" name="pickup_enabled" value="0">
                <input type="checkbox" name="pickup_enabled" value="1" @checked(old('pickup_enabled', $section->pickup_enabled ?? true))>
                Самовывоз разрешён для всех блюд раздела
            </label>
        </div>
        <div class="menu-form-group">
            <label class="menu-form-checkbox">
                <input type="hidden" name="dine_in_enabled" value="0">
                <input type="checkbox" name="dine_in_enabled" value="1" @checked(old('dine_in_enabled', $section->dine_in_enabled ?? true))>
                Заказ в заведении разрешён для всех блюд раздела
            </label>
        </div>

        <div class="menu-form-actions">

            <a
                href="{{ route($menuRoutePrefix . 'index', $organization) }}"
                class="menu-form-back"
            >
                Назад
            </a>

            <button
                type="submit"
                class="menu-form-button"
            >
                Сохранить
            </button>

        </div>

    </form>

</div>

@endsection