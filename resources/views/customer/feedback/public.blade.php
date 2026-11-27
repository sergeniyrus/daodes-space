@extends('customer.layouts.workspace')
@section('title_page') Отзывы · {{ $organization->name }} @endsection
@section('customer-content')
<style>
.customer-public-reviews{width:100%;max-width:none;box-sizing:border-box;margin:12px auto 28px;color:#f4f4f4}
.customer-public-reviews *{box-sizing:border-box}
.customer-public-reviews-panel{padding:22px;background:linear-gradient(145deg,#0b0c18,#14182a);border:1px solid rgba(255,215,0,.4);border-radius:16px;overflow-wrap:anywhere}
@media(max-width:600px){.customer-public-reviews-panel{padding:16px;border-radius:14px}}
</style>
<div class="customer-public-reviews">
    <section class="customer-public-reviews-panel">
        <h1 style="color:#ffd700">Отзывы · {{ $organization->name }}</h1>
        <p style="color:#00e5d4;font-size:1.2rem">{{ $ratingStats->total ? number_format((float)$ratingStats->average,1,',',' ') : '—' }} / 5 · {{ $ratingStats->total }} опубликованных отзывов</p>
        @forelse($reviews as $review)
            <article style="padding:15px 0;border-top:1px solid rgba(0,229,212,.2)">
                <div style="color:#ffd700">{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</div>
                <strong>Покупатель</strong>
                @if($review->comment)<p>{{ $review->comment }}</p>@endif
                <small style="color:#aeb5c4">{{ $review->published_at?->format('d.m.Y') }}</small>
            </article>
        @empty <p style="color:#aeb5c4">Пока нет опубликованных отзывов.</p> @endforelse
        {{ $reviews->links() }}
    </section>
</div>
@endsection
