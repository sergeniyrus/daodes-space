@extends('template')

@section('title_page')
    Новое блюдо — {{ $section->name }}

<script>
(() => {
    const input = document.getElementById('menu-image-input');
    const preview = document.getElementById('menu-image-preview');
    if (!input || !preview) return;
    let previewUrl = null;
    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
    });
    window.addEventListener('beforeunload', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
})();
</script>

@endsection

@section('main')

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

    margin-bottom: 8px;

    text-align: center;
}

.menu-form-subtitle {
    color: #888;

    text-align: center;

    margin-bottom: 25px;
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

.menu-form-input,
.menu-form-textarea {
    width: 100%;

    box-sizing: border-box;

    padding: 11px 14px;

    background: #1a1a1a;

    color: #fff;

    border: 1px solid #555;

    border-radius: 10px;

    outline: none;
}

.menu-form-input {
    min-height: 45px;
}

.menu-form-textarea {
    min-height: 110px;

    resize: vertical;
}

.menu-form-input:focus,
.menu-form-textarea:focus {
    border-color: gold;

    box-shadow:
        0 0 8px rgba(255, 215, 0, 0.25);
}

.menu-form-row {
    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;
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

@media (max-width: 600px) {

    .menu-form-container {
        margin: 20px 10px;

        padding: 18px;
    }

    .menu-form-row {
        grid-template-columns: 1fr;
    }

    .menu-form-actions {
        flex-direction: column;
    }

    .menu-form-button,
    .menu-form-back {
        width: 100%;

        box-sizing: border-box;

        justify-content: center;
    }
}


.menu-image-preview { display:block; width:100%; max-width:260px; aspect-ratio:4/3; object-fit:cover; margin:10px auto; border:1px solid rgba(0,229,212,.5); border-radius:13px; background:#090e1a; }
.menu-image-actions { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; }
.menu-image-actions label, .menu-image-remove { display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:7px 14px; border:1px solid #00e5d4; border-radius:20px; color:#00e5d4; background:rgba(0,229,212,.07); cursor:pointer; }
.menu-image-actions input[type=file] { display:none; }
.menu-availability-list { display:grid; grid-template-columns:1fr; gap:9px; padding:12px; border:1px solid rgba(0,229,212,.25); border-radius:12px; background:rgba(0,229,212,.035); }
.menu-availability-list .menu-form-checkbox { padding:5px 0; }

</style>


<div class="menu-form-container">

    <div class="menu-form-title">
        Новое блюдо
    </div>

    <div class="menu-form-subtitle">
        {{ $organization->name }} → {{ $section->name }}
    </div>


    <form
        method="POST"
        enctype="multipart/form-data"
        action="{{ route('organizations.menu.items.store', [
            'organization' => $organization,
            'section' => $section,
        ]) }}"
    >

        @csrf


        <div class="menu-form-group">

            <label class="menu-form-label">
                Название блюда
            </label>

            <input
                type="text"
                name="name"
                class="menu-form-input"
                value="{{ old('name') }}"
                required
                maxlength="255"
            >

        </div>


        <div class="menu-form-row">

            <div class="menu-form-group">

                <label class="menu-form-label">
                    Вес
                </label>

                <input
                    type="number"
                    step="0.001"
                    min="0"
                    name="weight"
                    class="menu-form-input"
                    value="{{ old('weight') }}"
                >

            </div>


            <div class="menu-form-group">

                <label class="menu-form-label">
                    Единица
                </label>

                <input
                    type="text"
                    name="unit"
                    class="menu-form-input"
                    value="{{ old('unit') }}"
                    maxlength="50"
                    placeholder="г / мл / шт"
                >

            </div>

        </div>


        <div class="menu-form-group">

            <label class="menu-form-label">
                Цена
            </label>

            <input
                type="number"
                step="0.01"
                min="0"
                name="price"
                class="menu-form-input"
                value="{{ old('price') }}"
                required
            >

        </div>


        <div class="menu-form-group">
            <label class="menu-form-label">Фотография блюда</label>
            <img id="menu-image-preview" class="menu-image-preview" src="{{ asset('images/org/' . $organization->id . '/not-img.png') }}" alt="Предпросмотр фотографии">
            <div class="menu-image-actions">
                <label for="menu-image-input">Выбрать изображение</label>
                <input id="menu-image-input" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </div>
            <small class="menu-form-label">JPG, PNG или WEBP, до 5 МБ.</small>
        </div>

        <div class="menu-form-group">

            <label class="menu-form-label">
                Описание
            </label>

            <textarea
                name="description"
                class="menu-form-textarea"
            >{{ old('description') }}</textarea>

        </div>


        <div class="menu-form-group">

            <label class="menu-form-label">
                Порядок
            </label>

            <input
                type="number"
                name="sort_order"
                class="menu-form-input"
                value="{{ old('sort_order', 0) }}"
                min="0"
            >

        </div>


        <div class="menu-form-group menu-availability-list">
            <label class="menu-form-checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Блюдо активно в меню</label>
            <label class="menu-form-checkbox"><input type="checkbox" name="delivery_enabled" value="1" @checked(old('delivery_enabled', true))> Доступно для доставки</label>
            <label class="menu-form-checkbox"><input type="checkbox" name="pickup_enabled" value="1" @checked(old('pickup_enabled', true))> Доступно для самовывоза</label>
        </div>


        <div class="menu-form-actions">

            <a
                href="{{ route('organizations.menu.index', $organization) }}"
                class="menu-form-back"
            >
                Назад
            </a>

            <button
                type="submit"
                class="menu-form-button"
            >
                Добавить блюдо
            </button>

        </div>

    </form>

</div>

@endsection