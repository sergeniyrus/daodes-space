@extends('organizations.layouts.management')
@section('title_page') Отзывы и обращения · {{ $organization->name }} @endsection
@section('management-content')
@include('components.order-notifications', [
    'notificationsUrl' => route('employee.organization.admin.orders.notifications', $organization),
    'notificationReadUrlTemplate' => route('employee.organization.admin.orders.notifications.read', [$organization, '__ID__']),
])
<style>
.feedback-page{width:100%;max-width:none;box-sizing:border-box;color:#f4f5fa}
.feedback-page *{box-sizing:border-box}
.feedback-page h1,.feedback-page h2,.feedback-page h3{color:#f5cf67}
.feedback-page h1{margin:0 0 7px;font-size:clamp(1.35rem,2.5vw,1.8rem)}
.feedback-page h2{margin:26px 0 13px;font-size:1.15rem}
.feedback-page-muted{color:#aeb8c8}
.feedback-page-intro{margin:0 0 18px;color:#aeb8c8;font-size:.92rem}
.feedback-page-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0 22px}
.feedback-page-stat{min-width:0;padding:15px 17px;border:1px solid rgba(0,229,212,.3);border-radius:13px;background:linear-gradient(145deg,rgba(0,229,212,.075),rgba(8,13,24,.5));box-shadow:inset 0 1px 0 rgba(255,215,0,.08)}
.feedback-page-stat span{display:block;color:#aeb8c8;font-size:.84rem}
.feedback-page-stat strong{display:block;margin-top:5px;color:#00e5d4;font-size:1.65rem;line-height:1.2}
.feedback-page-stars{color:#ffd700;font-size:1.12rem;letter-spacing:2px}
.feedback-page-panel{margin:14px 0;padding:16px;border:1px solid rgba(0,229,212,.24);border-radius:14px;background:rgba(5,12,23,.42)}
.feedback-page-rating-row{display:flex;align-items:center;gap:12px;margin:8px 0}
.feedback-page-rating-track{height:7px;flex:1;overflow:hidden;border-radius:10px;background:rgba(255,255,255,.09)}
.feedback-page-rating-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,#00bfae,#ffd700)}
.feedback-page-toolbar{display:flex;align-items:end;gap:10px;flex-wrap:wrap;margin:14px 0}
.feedback-page-field{display:grid;gap:5px;min-width:130px;color:#aeb8c8;font-size:.83rem}
.feedback-page input,.feedback-page select,.feedback-page textarea{width:100%;min-height:40px;padding:9px 11px;color:#f4f5fa;background:#0b1422;border:1px solid rgba(0,229,212,.34);border-radius:9px;outline:none}
.feedback-page input:focus,.feedback-page select:focus,.feedback-page textarea:focus{border-color:#00e5d4;box-shadow:0 0 0 2px rgba(0,229,212,.1)}
.feedback-page textarea{min-height:94px;resize:vertical}
.feedback-page-actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.feedback-page button,.feedback-page .feedback-page-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:39px;padding:9px 14px;color:#061416;background:linear-gradient(135deg,#00e5d4,#65f0df);border:1px solid rgba(0,229,212,.75);border-radius:9px;font-weight:750;font-size:.87rem;text-decoration:none;cursor:pointer;transition:filter .18s,transform .18s}
.feedback-page button:hover,.feedback-page .feedback-page-button:hover{filter:brightness(1.08);transform:translateY(-1px)}
.feedback-page .feedback-page-button-secondary{color:#d9e5ec;background:rgba(255,255,255,.045);border-color:rgba(0,229,212,.32)}
.feedback-page .feedback-page-button-gold{color:#211a06;background:linear-gradient(135deg,#f5cf67,#ffe6a0);border-color:rgba(255,215,0,.6)}
.feedback-page-card{margin:12px 0;padding:16px;border:1px solid rgba(0,229,212,.25);border-radius:14px;background:linear-gradient(145deg,rgba(16,27,43,.82),rgba(7,13,23,.8));box-shadow:inset 0 1px 0 rgba(255,215,0,.07)}
.feedback-page-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.feedback-page-card-title{color:#f5cf67;font-weight:750}
.feedback-page-meta{margin-top:7px;color:#aeb8c8;font-size:.83rem;line-height:1.6}
.feedback-page-message{margin:13px 0;color:#edf2f7;line-height:1.65;overflow-wrap:anywhere;white-space:pre-line}
.feedback-page-reply{margin:13px 0;padding:12px 14px;border:1px solid rgba(0,229,212,.2);border-left:3px solid #00e5d4;border-radius:9px;background:rgba(0,229,212,.055)}
.feedback-page-reply strong{color:#00e5d4}
.feedback-page-form{display:grid;gap:10px;margin-top:14px;padding-top:13px;border-top:1px solid rgba(0,229,212,.16)}
.feedback-page-empty{padding:18px;border:1px dashed rgba(0,229,212,.28);border-radius:12px;color:#aeb8c8;text-align:center}
.feedback-page-status{display:inline-flex;align-items:center;padding:4px 9px;border:1px solid rgba(0,229,212,.25);border-radius:20px;color:#9cebe2;background:rgba(0,229,212,.07);font-size:.76rem;white-space:nowrap}
.feedback-page-pagination{margin:16px 0}
.feedback-page-pagination nav{display:flex;justify-content:center}
@media(max-width:650px){.feedback-page-stats{grid-template-columns:1fr}.feedback-page-panel,.feedback-page-card{padding:13px}.feedback-page-toolbar{display:grid;grid-template-columns:1fr 1fr}.feedback-page-toolbar .feedback-page-field{min-width:0}.feedback-page-toolbar .feedback-page-actions{grid-column:1/-1}.feedback-page-card-head{display:block}}
</style>
<div class="feedback-page">
    <h1>Отзывы и обращения</h1>
    <p class="feedback-page-intro">Просмотр отзывов клиентов и работа с обращениями организации.</p>

    <div class="feedback-page-stats">
        <div class="feedback-page-stat"><span>Средняя оценка</span><strong>{{ $ratingStats->total ? number_format((float)$ratingStats->average,1,',',' ') : '—' }} / 5</strong><small class="feedback-page-muted">рейтинг организации</small></div>
        <div class="feedback-page-stat"><span>Всего оценок</span><strong>{{ $ratingStats->total }}</strong><small class="feedback-page-muted">отзывы о заказах</small></div>
        <div class="feedback-page-stat"><span>Обращений</span><strong>{{ $feedback->total() }}</strong><small class="feedback-page-muted">всего записей</small></div>
    </div>

    <h2>Отзывы заказов</h2>
    @forelse($reviews as $review)
        <article class="feedback-page-card">
            <div class="feedback-page-card-head">
                <strong class="feedback-page-card-title">Заказ №{{ $review->order?->order_number }} · {{ $review->customer?->name ?? 'Клиент' }}</strong>
                <span class="feedback-page-status">{{ $review->is_public ? 'Опубликован' : 'Не опубликован' }}</span>
            </div>
            <div class="feedback-page-stars" style="margin-top:9px">{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</div>
            <p class="feedback-page-message">{{ $review->comment ?: 'Без комментария' }}</p>
        </article>
    @empty
        <div class="feedback-page-empty">Оценок пока нет.</div>
    @endforelse
    <div class="feedback-page-pagination">{{ $reviews->links() }}</div>

    <h2>Книга предложений и обращений</h2>
    @forelse($feedback as $item)
        <article class="feedback-page-card">
            <div class="feedback-page-card-head">
                <div>
                    <div class="feedback-page-card-title">{{ $item->subject }}</div>
                    <div class="feedback-page-meta">{{ ['review'=>'Отзыв','suggestion'=>'Предложение','complaint'=>'Жалоба','thanks'=>'Благодарность'][$item->type] ?? $item->type }} · {{ $item->created_at?->format('d.m.Y H:i') }}</div>
                </div>
                <span class="feedback-page-status">{{ ['new'=>'Новое','in_progress'=>'Рассматривается','answered'=>'Отвечено','closed'=>'Закрыто'][$item->status] ?? $item->status }}</span>
            </div>
            <p class="feedback-page-message">{{ $item->message }}</p>
            <div class="feedback-page-meta">Клиент: {{ $item->customer?->name ?? 'Клиент' }} · {{ $item->customer?->phone ?? '' }} @if($item->order) · Заказ №{{ $item->order->order_number }} @endif</div>
            @if($item->reply)
                <div class="feedback-page-reply">
                    <strong>Ответ организации</strong>
                    <p class="feedback-page-message" style="margin:7px 0">{{ $item->reply }}</p>
                    <small class="feedback-page-muted">{{ $item->replied_at?->format('d.m.Y H:i') }}</small>
                </div>
            @endif
            @if($item->status !== 'closed')
                <form class="feedback-page-form" method="POST" action="{{ route('employee.organization.admin.feedback.reply', [$organization, $item]) }}">
                    @csrf
                    <textarea name="reply" rows="3" required maxlength="5000" placeholder="Напишите ответ клиенту">{{ old('reply') }}</textarea>
                    <div class="feedback-page-actions">
                        <label class="feedback-page-field" style="min-width:230px">После ответа
                            <select name="status"><option value="answered">Оставить обращение открытым</option><option value="closed">Ответить и закрыть</option></select>
                        </label>
                        <button type="submit">Отправить ответ</button>
                    </div>
                </form>
            @else
                <div class="feedback-page-meta">Обращение закрыто</div>
            @endif
        </article>
    @empty
        <div class="feedback-page-empty">Обращений пока нет.</div>
    @endforelse
    <div class="feedback-page-pagination">{{ $feedback->links() }}</div>
</div>
@endsection
