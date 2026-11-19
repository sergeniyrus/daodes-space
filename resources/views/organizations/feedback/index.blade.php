@extends('organizations.layouts.management')
@section('title_page') Отзывы и предложения · {{ $organization->name }} @endsection
@section('management-content')
@include('components.order-notifications', [
    'notificationsUrl' => route('organizations.orders.notifications', $organization),
    'notificationReadUrlTemplate' => route('organizations.orders.notifications.read', [$organization, '__ID__']),
])
<style>
.org-feedback{width:100%;max-width:none;box-sizing:border-box;margin:0;padding:0;color:#f4f4f4;background:transparent;border:0;border-radius:0}
.org-feedback h1,.org-feedback h2{color:#ffd700}.org-feedback-muted{color:#aeb5c4}
.org-feedback-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}
.org-feedback-stat{padding:16px;border:1px solid rgba(0,229,212,.25);border-radius:12px;background:rgba(0,229,212,.04)}
.org-feedback-stat strong{display:block;color:#00e5d4;font-size:1.6rem}
.org-feedback-card{padding:16px 0;border-top:1px solid rgba(0,229,212,.2)}
.org-feedback-stars{color:#ffd700;font-size:1.15rem;letter-spacing:2px}
.org-feedback-form{display:grid;gap:9px;margin-top:12px}
.org-feedback-form textarea,.org-feedback-form select{width:100%;padding:10px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.35);border-radius:9px}
.org-feedback button{padding:10px 15px;color:#061416;background:#00e5d4;border:0;border-radius:9px;font-weight:800;cursor:pointer}
.org-feedback a{color:#00e5d4;text-decoration:none}
@media(max-width:650px){.org-feedback-grid{grid-template-columns:1fr}.org-feedback{margin:12px;padding:14px}}
</style>
<div class="org-feedback">
    <p class="org-feedback-muted">{{ $organization->name }} · Панель менеджера</p>
    <h1>Отзывы и книга предложений</h1>
    <div class="org-feedback-grid">
        <div class="org-feedback-stat"><span class="org-feedback-muted">Средняя оценка</span><strong>{{ $ratingStats->total ? number_format((float)$ratingStats->average,1,',',' ') : '—' }} / 5</strong><small>{{ $ratingStats->total }} оценок</small></div>
        <div class="org-feedback-stat"><span class="org-feedback-muted">Новые обращения</span><strong>{{ $newFeedbackCount }}</strong><small>всего</small></div>
        <div class="org-feedback-stat"><span class="org-feedback-muted">Отзывы</span><strong>{{ $reviews->total() }}</strong><small>всего оценок заказов</small></div>
    </div>
    <h2>Распределение оценок</h2>
    @for($star=5;$star>=1;$star--)
        <div style="display:flex;gap:12px;align-items:center;margin:7px 0"><span class="org-feedback-stars">{{ str_repeat('★',$star) }}</span><span>{{ (int)($ratingDistribution[$star] ?? 0) }}</span></div>
    @endfor
    <form method="GET" style="display:flex;gap:9px;flex-wrap:wrap;margin:14px 0">
        <select name="status" style="padding:9px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.35);border-radius:9px">
            <option value="">Все статусы</option>
            @foreach(['new'=>'Новое','in_progress'=>'Рассматривается','answered'=>'Отвечено','closed'=>'Закрыто'] as $key=>$label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="type" style="padding:9px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.35);border-radius:9px">
            <option value="">Все типы</option>
            @foreach(['review'=>'Отзыв','suggestion'=>'Предложение','complaint'=>'Жалоба','thanks'=>'Благодарность'] as $key=>$label)
                <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="org-feedback-muted">С даты <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" style="padding:8px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.35);border-radius:8px"></label>
        <label class="org-feedback-muted">По дату <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" style="padding:8px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.35);border-radius:8px"></label>
        <button type="submit">Фильтровать обращения</button>
        <a href="{{ route('organizations.feedback.index', $organization) }}" style="align-self:center">Сбросить</a>
    </form>
    <h2 style="margin-top:28px">Книга отзывов и предложений</h2>
    @forelse($feedback as $item)
        <article class="org-feedback-card">
            <div><strong>{{ $item->subject }}</strong> <span class="org-feedback-muted">· {{ ['review'=>'Отзыв','suggestion'=>'Предложение','complaint'=>'Жалоба','thanks'=>'Благодарность'][$item->type] ?? $item->type }} · {{ $item->created_at?->format('d.m.Y H:i') }}</span></div>
            <p>{{ $item->message }}</p>
            <div class="org-feedback-muted">Клиент: {{ $item->customer?->name ?? 'Клиент' }} · {{ $item->customer?->phone ?? '' }} @if($item->order) · Заказ №{{ $item->order->order_number }} @endif</div>
            @if($item->reply)<blockquote style="border-left:3px solid #00e5d4;padding-left:12px">{{ $item->reply }}</blockquote>@endif
            @if($item->status !== 'closed')
                <form class="org-feedback-form" method="POST" action="{{ route('organizations.feedback.reply', [$organization,$item]) }}">
                    @csrf
                    <textarea name="reply" rows="3" required maxlength="5000" placeholder="Ответ клиенту">{{ old('reply') }}</textarea>
                    <select name="status"><option value="answered">Ответить и оставить открытым</option><option value="closed">Ответить и закрыть обращение</option></select>
                    <button type="submit">Отправить ответ</button>
                </form>
            @else <span class="org-feedback-muted">Обращение закрыто</span> @endif
        </article>
    @empty <p class="org-feedback-muted">Обращений пока нет.</p> @endforelse
    {{ $feedback->links() }}
    <form method="GET" style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin:12px 0">
        <label class="org-feedback-muted">Оценка:
            <select name="rating" style="padding:8px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.35);border-radius:8px">
                <option value="">Любая</option>
                @for($star=5;$star>=1;$star--)
                    <option value="{{ $star }}" @selected((string)($filters['rating'] ?? '') === (string)$star)>{{ $star }} ★</option>
                @endfor
            </select>
        </label>
        <button type="submit">Фильтровать оценки</button>
    </form>
    <h2 style="margin-top:28px">Оценки заказов</h2>
    @forelse($reviews as $review)
        <article class="org-feedback-card">
            <strong>Заказ №{{ $review->order?->order_number }} · {{ $review->customer?->name ?? 'Клиент' }}</strong>
            <div class="org-feedback-stars">{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</div>
            <p>{{ $review->comment ?: 'Без комментария' }}</p>
            <form method="POST" action="{{ route('organizations.feedback.reviews.visibility',[$organization,$review]) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="is_public" value="{{ $review->is_public ? 0 : 1 }}">
                <button type="submit">{{ $review->is_public ? 'Скрыть публичный отзыв' : 'Опубликовать отзыв' }}</button>
            </form>
        </article>
    @empty <p class="org-feedback-muted">Оценок пока нет.</p> @endforelse
    {{ $reviews->links() }}
</div>
@endsection
