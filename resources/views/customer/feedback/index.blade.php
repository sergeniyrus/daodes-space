@extends('customer.layouts.workspace')

@section('title_page') Мои отзывы и предложения @endsection

@section('customer-content')
<style>
.feedback-page{max-width:1000px;margin:25px auto 50px;color:#f4f4f4}
.feedback-panel{padding:22px;margin-bottom:16px;background:linear-gradient(145deg,#0b0c18,#14182a 58%,#0b0c18);border:1px solid rgba(255,215,0,.4);border-radius:18px;box-shadow:0 12px 35px rgba(0,0,0,.3)}
.feedback-page h1,.feedback-page h2{color:#ffd700;margin-top:0}
.feedback-muted{color:#aeb5c4}.feedback-row{padding:15px 0;border-bottom:1px solid rgba(0,229,212,.18)}
.feedback-form{display:grid;gap:12px}.feedback-form label{display:block;color:#00e5d4;margin-bottom:5px}
.feedback-form input,.feedback-form select,.feedback-form textarea{width:100%;padding:11px 12px;color:#fff;background:#0c1422;border:1px solid rgba(0,229,212,.35);border-radius:10px}
.feedback-form button{padding:12px 18px;color:#081416;background:#00e5d4;border:0;border-radius:10px;font-weight:800;cursor:pointer}
.feedback-link{color:#00e5d4;text-decoration:none}
.feedback-stars{color:#ffd700;letter-spacing:2px}
</style>
<div class="feedback-page">
    @include('components.customer-push-subscription')
    @include('components.order-notifications', [
        'notificationsUrl' => route('customer.orders.notifications'),
        'notificationReadUrlTemplate' => route('customer.orders.notifications.read', '__ID__'),
        'notificationReadAllUrl' => route('customer.orders.notifications.read-all'),
    ])
    @if(session('success'))<div class="feedback-panel" style="border-color:#00e5a8;color:#00e5a8">{{ session('success') }}</div>@endif
    <section class="feedback-panel">
        <h1>Книга отзывов и предложений</h1>
        <p class="feedback-muted">Расскажите организации о впечатлениях, предложениях или проблеме. Обращение увидят менеджер и администраторы.</p>
        <form class="feedback-form" method="POST" action="{{ $selectedOrganization ? route('customer.feedback.store', $selectedOrganization) : route('customer.feedback.store', ['organization' => '__ORG__']) }}" id="customer-feedback-form">
            @csrf
            @if($selectedOrganization)
                <div class="feedback-muted" style="padding:10px 12px;border:1px solid rgba(0,229,212,.25);border-radius:10px;background:rgba(0,229,212,.04)">
                    Обращение направляется организации: <strong style="color:#ffd700">{{ $selectedOrganization->name }}</strong>
                </div>
            @else
                <label>Организация
                    <select id="feedback-organization" required>
                        <option value="">Выберите кафе</option>
                        @foreach($organizations as $organization)
                            <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>Тип обращения
                <select name="type" required>
                    <option value="review">Отзыв</option><option value="suggestion">Предложение</option><option value="complaint">Жалоба</option><option value="thanks">Благодарность</option>
                </select>
            </label>
            <label>Тема<input name="subject" maxlength="180" required value="{{ old('subject') }}" placeholder="Кратко опишите тему"></label>
            <label>Сообщение<textarea name="message" rows="5" maxlength="5000" required>{{ old('message') }}</textarea></label>
            <label>Номер заказа (необязательно)
                <select name="order_id" id="feedback-order"><option value="">Без привязки к заказу</option>@foreach($customerOrders as $customerOrder)<option value="{{ $customerOrder->id }}" data-organization="{{ $customerOrder->organization_id }}">№{{ $customerOrder->order_number }} · {{ $customerOrder->organization?->name }} · {{ $customerOrder->created_at?->format('d.m.Y') }}</option>@endforeach</select>
            </label>
            <button type="submit">Отправить обращение</button>
        </form>
        @error('organization')<p style="color:#ff8888">{{ $message }}</p>@enderror
        @error('message')<p style="color:#ff8888">{{ $message }}</p>@enderror
    </section>
    <section class="feedback-panel">
        <h2>Мои обращения</h2>
        @forelse($feedback as $item)
            <article class="feedback-row">
                <strong>{{ $item->subject }}</strong>
                <span class="feedback-muted"> · {{ $item->organization?->name }} · {{ $item->created_at?->format('d.m.Y H:i') }}</span>
                <p>{{ $item->message }}</p>
                <small class="feedback-muted">Статус: {{ ['new'=>'Новое','in_progress'=>'Рассматривается','answered'=>'Отвечено','closed'=>'Закрыто'][$item->status] ?? $item->status }}</small>
                @if($item->reply)<div style="margin-top:10px;padding:12px;border-left:3px solid #00e5d4;background:rgba(0,229,212,.06)"><strong style="color:#00e5d4">Ответ организации</strong><p>{{ $item->reply }}</p><small class="feedback-muted">{{ $item->replied_at?->format('d.m.Y H:i') }}</small></div>@endif
            </article>
        @empty <p class="feedback-muted">Вы ещё не отправляли обращений.</p> @endforelse
        {{ $feedback->links() }}
    </section>
    <section class="feedback-panel">
        <h2>Мои оценки заказов</h2>
        @forelse($reviews as $review)
            <article class="feedback-row">
                <strong>{{ $review->organization?->name }} · Заказ №{{ $review->order?->order_number }}</strong>
                <div class="feedback-stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                @if($review->comment)<p>{{ $review->comment }}</p>@endif
            </article>
        @empty <p class="feedback-muted">Пока нет оценок.</p> @endforelse
        {{ $reviews->links() }}
    </section>
</div>
<script>
(() => {
    const form = document.getElementById('customer-feedback-form');
    if (!form) return;

    const select = document.getElementById('feedback-organization');
    const orderSelect = document.getElementById('feedback-order');
    const allOrderOptions = orderSelect ? Array.from(orderSelect.querySelectorAll('option[data-organization]')) : [];

    if (!select) return;

    const template = @json(route('customer.feedback.store', ['organization' => '__ORG__']));

    function filterOrders() {
        if (!orderSelect) return;
        allOrderOptions.forEach(option => { option.hidden = option.dataset.organization !== select.value; });
        if (orderSelect.selectedOptions[0]?.hidden) orderSelect.value = '';
    }

    filterOrders();
    select.addEventListener('change', filterOrders);
    select.addEventListener('change', () => {
        form.action = template.replace('__ORG__', encodeURIComponent(select.value));
    });
    form.addEventListener('submit', (event) => {
        if (!select.value) { event.preventDefault(); alert('Выберите организацию.'); }
    });
})();
</script>
@endsection
