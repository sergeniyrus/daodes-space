@extends('template')
@section('title_page') Отзывы · {{ $organization->name }} @endsection
@section('main')
@include('components.order-notifications', [
    'notificationsUrl' => route('employee.organization.admin.orders.notifications', $organization),
    'notificationReadUrlTemplate' => route('employee.organization.admin.orders.notifications.read', [$organization, '__ID__']),
])
@include('employee.admin.partials.navigation', ['organization' => $organization])
<style>
.admin-feedback{max-width:1100px;margin:25px auto;padding:20px;color:#f4f4f4;background:linear-gradient(145deg,#0b0c18,#14182a);border:1px solid rgba(255,215,0,.4);border-radius:18px}
.admin-feedback h1,.admin-feedback h2{color:#ffd700}.admin-feedback-muted{color:#aeb5c4}.admin-feedback-card{padding:14px 0;border-top:1px solid rgba(0,229,212,.2)}
.admin-feedback-stars{color:#ffd700;letter-spacing:2px}.admin-feedback-stat{display:inline-block;min-width:190px;padding:14px;margin:8px 8px 8px 0;border:1px solid rgba(0,229,212,.25);border-radius:12px}
.admin-feedback-stat strong{display:block;color:#00e5d4;font-size:1.5rem}
</style>
<div class="admin-feedback">
    <p class="admin-feedback-muted">{{ $organization->name }} · Администратор</p>
    <h1>Отзывы и обращения</h1>
    <p class="admin-feedback-muted">Режим просмотра. Ответы и управление обращениями доступны менеджеру организации.</p>
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
        <article class="admin-feedback-card"><strong>{{ $item->subject }}</strong><p>{{ $item->message }}</p><small class="admin-feedback-muted">{{ $item->customer?->name ?? 'Клиент' }} · {{ $item->created_at?->format('d.m.Y H:i') }} · {{ $item->status }}</small>@if($item->reply)<blockquote>{{ $item->reply }}</blockquote>@endif</article>
    @empty <p class="admin-feedback-muted">Обращений пока нет.</p> @endforelse
    {{ $feedback->links() }}
</div>
@endsection
