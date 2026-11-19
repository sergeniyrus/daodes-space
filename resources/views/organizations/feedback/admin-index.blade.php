@extends('organizations.layouts.management')
@section('title_page') Отзывы · {{ $organization->name }} @endsection
@section('management-content')
@include('components.order-notifications', [
    'notificationsUrl' => route('employee.organization.admin.orders.notifications', $organization),
    'notificationReadUrlTemplate' => route('employee.organization.admin.orders.notifications.read', [$organization, '__ID__']),
])
<style>
.admin-feedback{width:100%;max-width:none;box-sizing:border-box;margin:0;padding:0;color:#f4f4f4;background:transparent;border:0;border-radius:0}
.admin-feedback h1,.admin-feedback h2{color:#ffd700}.admin-feedback-muted{color:#aeb5c4}.admin-feedback-card{padding:14px 0;border-top:1px solid rgba(0,229,212,.2)}
.admin-feedback-stars{color:#ffd700;letter-spacing:2px}.admin-feedback-stat{display:inline-block;min-width:190px;padding:14px;margin:8px 8px 8px 0;border:1px solid rgba(0,229,212,.25);border-radius:12px}
.admin-feedback-stat strong{display:block;color:#00e5d4;font-size:1.5rem}
</style>
<div class="admin-feedback">
    <p class="admin-feedback-muted">АДМИНИСТРАТОР · ОТЗЫВЫ</p>
    <h1>Отзывы и обращения</h1>
    <p class="admin-feedback-muted">Администратор может просматривать обращения и отправлять ответы клиентам. Управление остальными настройками остаётся у менеджера.</p>
    <div class="admin-feedback-stat"><span class="admin-feedback-muted">Средний рейтинг</span><strong>{{ $ratingStats->total ? number_format((float)$ratingStats->average,1,',',' ') : '—' }} / 5</strong></div>
    <div class="admin-feedback-stat"><span class="admin-feedback-muted">Оценок</span><strong>{{ $ratingStats->total }}</strong></div>
    <div class="admin-feedback-stat"><span class="admin-feedback-muted">Обращений</span><strong>{{ $feedback->total() }}</strong></div>
    <h2>Отзывы заказов</h2>
    @forelse($reviews as $review)
        <article class="admin-feedback-card"><strong>Заказ №{{ $review->order?->order_number }} · {{ $review->customer?->name ?? 'Клиент' }}</strong><div class="admin-feedback-stars">{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</div><p>{{ $review->comment ?: 'Без комментария' }}</p><small class="admin-feedback-muted">{{ $review->is_public ? 'Опубликован' : 'Не опубликован' }}</small></article>
    @empty <p class="admin-feedback-muted">Оценок пока нет.</p> @endforelse
    {{ $reviews->links() }}
    <h2>Книга отзывов и предложений</h2>
    @forelse($feedback as $item)
        <article class="admin-feedback-card">
            <strong>{{ $item->subject }}</strong>
            <p>{{ $item->message }}</p>
            <small class="admin-feedback-muted">{{ $item->customer?->name ?? 'Клиент' }} · {{ $item->created_at?->format('d.m.Y H:i') }} · {{ ['new'=>'Новое','in_progress'=>'Рассматривается','answered'=>'Отвечено','closed'=>'Закрыто'][$item->status] ?? $item->status }}</small>
            @if($item->reply)
                <blockquote style="margin:12px 0;padding:10px 13px;border-left:3px solid #00e5d4;background:rgba(0,229,212,.06)">
                    <strong style="color:#00e5d4">Ответ организации</strong>
                    <p>{{ $item->reply }}</p>
                    <small class="admin-feedback-muted">{{ $item->replied_at?->format('d.m.Y H:i') }}</small>
                </blockquote>
            @endif
            @if($item->status !== 'closed')
                <form method="POST" action="{{ route('employee.organization.admin.feedback.reply', [$organization, $item]) }}" style="display:grid;gap:9px;margin-top:12px">
                    @csrf
                    <textarea name="reply" rows="3" required maxlength="5000" placeholder="Ответ клиенту" style="width:100%;padding:10px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.35);border-radius:9px">{{ old('reply') }}</textarea>
                    <select name="status" style="padding:9px;color:#fff;background:#0b1422;border:1px solid rgba(0,229,212,.35);border-radius:9px">
                        <option value="answered">Ответить и оставить открытым</option>
                        <option value="closed">Ответить и закрыть обращение</option>
                    </select>
                    <button type="submit">Отправить ответ клиенту</button>
                </form>
            @else
                <span class="admin-feedback-muted">Обращение закрыто</span>
            @endif
        </article>
    @empty <p class="admin-feedback-muted">Обращений пока нет.</p> @endforelse
    {{ $feedback->links() }}
</div>
@endsection
