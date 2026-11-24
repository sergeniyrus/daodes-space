@extends('template')

@section('title_page', 'Контакты DAODES')

@section('main')
<style>
.daodes-contacts {
    width: min(100% - 28px, 1080px);
    margin: 30px auto 48px;
    padding: 28px;
    color: #e8edf5;
    background: linear-gradient(145deg, #0b0c18, #111323 55%, #0b0c18);
    border: 1px solid rgba(255, 215, 0, .62);
    border-radius: 20px;
    box-shadow: 0 12px 36px rgba(0,0,0,.48);
}
.daodes-contacts h1, .daodes-contacts h2 { color: #ffd700; }
.daodes-contacts h1 { margin: 0 0 8px; text-align: center; }
.daodes-contacts-subtitle { color: #aeb9ca; text-align: center; margin: 0 0 24px; }
.daodes-contacts-search { display: flex; gap: 10px; margin: 0 auto 26px; max-width: 720px; }
.daodes-contacts-search input {
    flex: 1; min-width: 0; padding: 12px 14px; color: #fff;
    background: #090d18; border: 1px solid rgba(0,229,255,.45); border-radius: 10px;
}
.daodes-contacts button, .daodes-contacts .contact-action {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    padding: 9px 13px; color: #00e5ff; background: rgba(0,229,255,.055);
    border: 1px solid rgba(0,229,255,.45); border-radius: 9px; text-decoration: none;
    cursor: pointer; font: inherit;
}
.daodes-contacts button:hover, .daodes-contacts .contact-action:hover {
    color: #ffd700; border-color: rgba(255,215,0,.65);
}
.daodes-contacts .danger { color: #ff9292; border-color: rgba(255,100,100,.4); }
.daodes-contact-section { margin: 24px 0; }
.daodes-contact-section h2 { margin: 0 0 12px; font-size: 1.15rem; }
.daodes-contact-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 10px; }
.daodes-contact-card {
    display: flex; align-items: center; gap: 12px; min-width: 0; padding: 12px;
    background: rgba(255,255,255,.025); border: 1px solid rgba(0,229,255,.16); border-radius: 12px;
}
.daodes-contact-card img {
    width: 44px; height: 44px; flex: 0 0 44px; object-fit: cover; border-radius: 50%;
    border: 1px solid rgba(255,215,0,.45);
}
.daodes-contact-person { flex: 1; min-width: 0; }
.daodes-contact-person strong { display: block; overflow: hidden; text-overflow: ellipsis; }
.daodes-contact-person small { display: block; color: #9aa8bc; overflow: hidden; text-overflow: ellipsis; }
.daodes-contact-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
.daodes-contact-actions form { display: inline-flex; margin: 0; }
.daodes-contact-flash { padding: 11px 14px; margin: 12px 0; border-radius: 9px; border: 1px solid rgba(0,229,255,.28); }
.daodes-contact-flash.error { color: #ffaaaa; border-color: rgba(255,100,100,.4); }
.daodes-contact-empty { color: #9aa8bc; padding: 12px 0; }
@media (max-width: 600px) {
    .daodes-contacts { padding: 18px 13px; margin-top: 14px; }
    .daodes-contacts-search { flex-direction: column; }
    .daodes-contact-card { align-items: flex-start; flex-wrap: wrap; }
    .daodes-contact-actions { width: 100%; justify-content: flex-start; padding-left: 56px; }
}
</style>

<div class="daodes-contacts">
    <h1>Контакты и друзья</h1>
    <p class="daodes-contacts-subtitle">Находите пользователей DAODES, добавляйте их в контакты и делитесь историями.</p>

    @if (session('status'))
        <div class="daodes-contact-flash">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="daodes-contact-flash error">{{ session('error') }}</div>
    @endif

    <form class="daodes-contacts-search" method="GET" action="{{ route('contacts.index') }}">
        <input type="search" name="q" value="{{ $search }}" placeholder="Поиск по имени или @нику" autocomplete="off">
        <button type="submit"><i class="fa fa-search" aria-hidden="true"></i> Найти</button>
    </form>

    @if ($search !== '')
        <section class="daodes-contact-section">
            <h2>Результаты поиска</h2>
            <div class="daodes-contact-list">
                @forelse ($searchResults as $person)
                    <article class="daodes-contact-card">
                        <img src="{{ $person->profile?->avatar_url ?: '/img/main/default-avatar.png' }}" alt="">
                        <div class="daodes-contact-person">
                            <strong>{{ $person->name }}</strong>
                            <small>{{ $person->profile?->nickname ? '@' . $person->profile->nickname : 'Пользователь DAODES' }}</small>
                        </div>
                        <div class="daodes-contact-actions">
                            <form method="POST" action="{{ route('contacts.requests.store', $person->id) }}">
                                @csrf
                                <button type="submit">＋ Добавить</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="daodes-contact-empty">Пользователи не найдены или уже находятся в ваших контактах.</p>
                @endforelse
            </div>
        </section>
    @endif

    <section class="daodes-contact-section">
        <h2>Мои контакты ({{ $contacts->count() }})</h2>
        <div class="daodes-contact-list">
            @forelse ($contacts as $person)
                <article class="daodes-contact-card">
                    <img src="{{ $person->profile?->avatar_url ?: '/img/main/default-avatar.png' }}" alt="">
                    <div class="daodes-contact-person">
                        <strong>{{ $person->name }}</strong>
                        <small>{{ $person->profile?->nickname ? '@' . $person->profile->nickname : 'Контакт DAODES' }}</small>
                    </div>
                    <div class="daodes-contact-actions">
                        <form method="POST" action="{{ route('contacts.destroy', $person->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger" onclick="return confirm('Удалить пользователя из контактов?')">Удалить</button>
                        </form>
                        <form method="POST" action="{{ route('contacts.block', $person->id) }}">
                            @csrf
                            <button type="submit" class="danger">Блокировать</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="daodes-contact-empty">Пока нет добавленных контактов. Найдите пользователя выше.</p>
            @endforelse
        </div>
    </section>

    <section class="daodes-contact-section">
        <h2>Входящие заявки ({{ $incomingRequests->count() }})</h2>
        <div class="daodes-contact-list">
            @forelse ($incomingRequests as $item)
                <article class="daodes-contact-card">
                    <img src="{{ $item->avatar_url ?: '/img/main/default-avatar.png' }}" alt="">
                    <div class="daodes-contact-person">
                        <strong>{{ $item->name }}</strong>
                        <small>{{ $item->nickname ? '@' . $item->nickname : 'Хочет добавить вас в контакты' }}</small>
                    </div>
                    <div class="daodes-contact-actions">
                        <form method="POST" action="{{ route('contacts.requests.accept', $item->id) }}">
                            @csrf
                            <button type="submit">Принять</button>
                        </form>
                        <form method="POST" action="{{ route('contacts.requests.decline', $item->id) }}">
                            @csrf
                            <button type="submit" class="danger">Отклонить</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="daodes-contact-empty">Новых заявок нет.</p>
            @endforelse
        </div>
    </section>

    <section class="daodes-contact-section">
        <h2>Отправленные заявки ({{ $outgoingRequests->count() }})</h2>
        <div class="daodes-contact-list">
            @forelse ($outgoingRequests as $item)
                <article class="daodes-contact-card">
                    <img src="{{ $item->avatar_url ?: '/img/main/default-avatar.png' }}" alt="">
                    <div class="daodes-contact-person">
                        <strong>{{ $item->name }}</strong>
                        <small>{{ $item->nickname ? '@' . $item->nickname : 'Ожидает подтверждения' }}</small>
                    </div>
                </article>
            @empty
                <p class="daodes-contact-empty">Нет ожидающих заявок.</p>
            @endforelse
        </div>
    </section>

    @if ($blockedUsers->isNotEmpty())
        <section class="daodes-contact-section">
            <h2>Заблокированные</h2>
            <div class="daodes-contact-list">
                @foreach ($blockedUsers as $person)
                    <article class="daodes-contact-card">
                        <img src="{{ $person->profile?->avatar_url ?: '/img/main/default-avatar.png' }}" alt="">
                        <div class="daodes-contact-person"><strong>{{ $person->name }}</strong></div>
                        <form method="POST" action="{{ route('contacts.unblock', $person->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Разблокировать</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <div style="text-align:center;margin-top:24px">
        <a class="contact-action" href="{{ route('chats.index') }}">← Вернуться к чатам</a>
    </div>
</div>
@endsection
