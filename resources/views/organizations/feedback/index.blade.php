@extends('template')
@section('title_page') Отзывы и предложения · {{ $organization->name }} @endsection
@section('main')
<style>
.org-feedback{max-width:1200px;margin:25px auto 50px;padding:20px;color:#f4f4f4;background:linear-gradient(145deg,#0b0c18,#14182a 60%,#0b0c18);border:1px solid rgba(255,215,0,.45);border-radius:18px}
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
    @if(session('success'))<p style="color:#00e5a8">{{ session('success') }}</p>@endif
    <div class="org-feedback-grid">
        <div class="org-feedback-stat"><span class="org-feedback-muted">Средняя оценка</span><strong>{{ $ratingStats->total ? number_format((float)$ratingStats->average,1,',',' ') : '—' }} / 5</strong><small>{{ $ratingStats->total }} оценок</small></div>
        <div class="org-feedback-stat"><span class="org-feedback-muted">Новые обращения</span><strong>{{ $newFeedbackCount }}</strong><small>всего</small></div>
        <div class="org-feedback-stat"><span class="org-feedback-muted">Отзывы</span><strong>{{ $reviews->total() }}</strong><small>всего оценок заказов</small></div>
    </div>
    <h2>Распределение оценок</h2>
    @for($star=5;$star>=1;$star--)
        <div style="display:flex;gap:12px;align-items:center;margin:7px 0"><span class="org-feedback-stars">{{ str_repeat('★',$star) }}</span><span>{{ (int)($ratingDistribution[$star] ?? 0) }}</span></div>
    @endfor
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
