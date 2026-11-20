@extends('organizations.layouts.management')

@section('title_page')
    Способы оплаты iiko — {{ $organization->name }}
@endsection

@section('management-content')
<style>
.iiko-payments{max-width:1080px;margin:0 auto;color:#f4f6fb}
.iiko-payments-shell{padding:clamp(16px,2.5vw,26px);border:1px solid rgba(255,215,0,.38);border-radius:20px;background:linear-gradient(145deg,#0b0c18,#111626 55%,#0b0c18)}
.iiko-payments h1,.iiko-payments h2{color:#ffd700;margin-top:0}
.iiko-payments p{color:#aeb8c8;line-height:1.55}
.iiko-payments-card{padding:16px;border:1px solid rgba(0,229,212,.22);border-radius:13px;background:rgba(12,18,31,.78);margin-top:13px}
.iiko-payments-row{display:grid;grid-template-columns:1fr minmax(220px,1fr) auto;gap:12px;align-items:end;padding:12px 0;border-bottom:1px solid rgba(0,229,212,.12)}
.iiko-payments label{display:block;color:#c1c9d7;font-size:.85rem;margin-bottom:6px}
.iiko-payments select{width:100%;min-height:42px;padding:8px 10px;border:1px solid #39495a;border-radius:9px;background:#0b1220;color:#f5f6fa}
.iiko-payments button,.iiko-payments a{display:inline-flex;align-items:center;justify-content:center;min-height:41px;padding:8px 14px;border:1px solid #00e5d4;border-radius:10px;background:rgba(0,229,212,.05);color:#00e5d4;text-decoration:none;font-weight:700;cursor:pointer}
.iiko-payments button:hover,.iiko-payments a:hover{background:#00e5d4;color:#071316}
.iiko-payments .muted{font-size:.8rem;color:#91a0b3}
@media(max-width:700px){.iiko-payments-row{grid-template-columns:1fr}.iiko-payments button,.iiko-payments a{width:100%}}
</style>
<div class="iiko-payments">
    <div class="iiko-payments-shell">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1>Способы оплаты iiko</h1>
                <p>Сопоставление методов оплаты DAODES с платёжными типами конкретного ресторана.</p>
            </div>
            <a href="{{ route('organizations.iiko.index', $organization) }}">← Настройки iiko</a>
        </div>

        @if (session('success'))
            <p style="color:#76e1ad">{{ session('success') }}</p>
        @endif
        @if ($errors->has('iiko'))
            <p style="color:#ff9ca4">{{ $errors->first('iiko') }}</p>
        @endif

        <div class="iiko-payments-card">
            <h2>Справочник iiko</h2>
            <p>Сопоставление сохраняет идентификаторы платёжных типов. Оно само по себе не отмечает заказ оплаченным и не списывает деньги.</p>
            @php
                $methods = [
                    'cash_on_receipt' => 'Наличные при получении',
                    'card_on_receipt' => 'Банковская карта при получении',
                    'online' => 'Онлайн-оплата',
                    'guest_account' => 'Счёт гостя / браслет',
                ];
            @endphp

            @foreach ($methods as $method => $label)
                @php($mapping = $paymentMappings->get($method))
                <form class="iiko-payments-row" method="POST" action="{{ route('organizations.iiko.payments.save', $organization) }}">
                    @csrf
                    <input type="hidden" name="daodes_payment_method" value="{{ $method }}">
                    <div>
                        <strong>{{ $label }}</strong>
                        @if ($mapping)
                            <div class="muted">Сейчас: {{ $mapping->iiko_payment_type_name }} ({{ $mapping->payment_type_kind ?: 'тип не указан' }})</div>
                        @else
                            <div class="muted">Не сопоставлено</div>
                        @endif
                    </div>
                    <div>
                        <label for="payment_{{ $method }}">Платёжный тип iiko</label>
                        <select id="payment_{{ $method }}" name="iiko_payment_type_id" required>
                            <option value="">Выберите тип оплаты</option>
                            @foreach ($paymentTypes as $type)
                                <option value="{{ $type['id'] }}" @selected($mapping && $mapping->iiko_payment_type_id === $type['id'])>
                                    {{ $type['name'] }}{{ $type['kind'] ? ' · ' . $type['kind'] : '' }}{{ $type['is_default'] ? ' · по умолчанию' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit">Сохранить</button>
                </form>
            @endforeach
        </div>

        <p class="muted">Фактическая передача платежей будет включена отдельно: для наличных и карты при получении — после подтверждения оплаты, для онлайн — после подтверждённого webhook платёжного провайдера, для счёта гостя — после проверки схемы iiko Loyalty.</p>
    </div>
</div>
@endsection
