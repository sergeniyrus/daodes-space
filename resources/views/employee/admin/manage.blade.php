@extends('template')

@section('title_page') Настройки организации — {{ $organization->name }} @endsection

@section('main')
@include('components.order-notifications', [
    'notificationsUrl' => route('employee.organization.admin.orders.notifications', $organization),
    'notificationReadUrlTemplate' => route('employee.organization.admin.orders.notifications.read', [$organization, '__ID__']),
])

<style>
.admin-settings{width:min(1050px,calc(100% - 24px));margin:25px auto;color:#eee}.admin-settings section{padding:18px;margin-bottom:15px;background:linear-gradient(145deg,#0b0c18,#14182a);border:1px solid rgba(255,215,0,.4);border-radius:15px}.admin-settings h1,.admin-settings h2{color:#ffd700}.admin-settings label{display:inline-flex;gap:7px;align-items:center;margin:7px 12px 7px 0}.admin-settings input,.admin-settings select{padding:8px;color:#fff;background:#101522;border:1px solid #52606f;border-radius:7px}.admin-settings input[type=checkbox]{accent-color:#00e5d4}.admin-settings button,.admin-settings a.btn{display:inline-block;padding:8px 13px;margin:5px 4px 5px 0;border:1px solid #00e5d4;border-radius:18px;background:transparent;color:#00e5d4;text-decoration:none;cursor:pointer}.admin-settings .danger{border-color:#ff7777;color:#ff7777}.admin-settings .screen{padding:13px 0;border-top:1px solid rgba(0,229,212,.2)}.admin-settings .muted{color:#aeb5c4}
</style>
<div class="admin-settings">
 <section>
  <p><a href="{{ route('employee.organization.menu.index',$organization) }}">← Управление меню</a> · <a href="{{ route('employee.organization.admin.orders.index',$organization) }}">Заказы</a></p>
  <h1>Управление организацией</h1>
  <p class="muted">Администратор может управлять настройками заказов и TV-экранами. Управление персоналом доступно только менеджеру.</p>
 </section>
 <section>
  <h2>Настройки онлайн-заказов</h2>
  <form method="POST" action="{{ route('employee.organization.admin.order-settings.update',$organization) }}">@csrf @method('PUT')
   <label>Валюта <input name="currency" maxlength="3" value="{{ old('currency',$orderSettings?->currency ?? 'RUB') }}" required></label>
   <label>Стоимость доставки <input type="number" step="0.01" min="0" name="delivery_fee" value="{{ old('delivery_fee',$orderSettings?->delivery_fee ?? 0) }}" required></label>
   <label>Минимальный заказ <input type="number" step="0.01" min="0" name="minimum_order" value="{{ old('minimum_order',$orderSettings?->minimum_order ?? 0) }}"></label>
   <label>Минут до самовывоза <input type="number" min="0" max="1440" name="pickup_min_minutes" value="{{ old('pickup_min_minutes',$orderSettings?->pickup_min_minutes ?? 0) }}"></label>
   <div>
    @foreach(['orders_enabled'=>'Приём заказов','delivery_enabled'=>'Доставка','pickup_enabled'=>'Самовывоз','dine_in_enabled'=>'В заведении','online_payment_enabled'=>'Онлайн-оплата','cash_payment_enabled'=>'Наличные','card_payment_enabled'=>'Картой при получении'] as $field=>$label)
     <label><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, data_get($orderSettings, $field, false)))> {{ $label }}</label>
    @endforeach
   </div>
   <button type="submit">Сохранить настройки</button>
  </form>
 </section>
 <section>
  <h2>TV-экраны организации</h2>
  <form method="POST" action="{{ route('employee.organization.admin.tv.screens.store',$organization) }}">@csrf<button type="submit">+ Создать TV-экран</button></form>
  @foreach($tvScreens as $screen)
   @php($config=$tvScreensConfig[$screen] ?? [])
   <div class="screen">
    <h3>Экран {{ $screen }} · <a target="_blank" rel="noopener" href="{{ route('organization.tv',$organization) }}?screen={{ $screen }}">Открыть экран</a></h3>
    <form method="POST" action="{{ route('employee.organization.admin.tv.screens.settings',[$organization,$screen]) }}">@csrf @method('PATCH')
     <label>Название <input name="screen_name" maxlength="60" value="{{ $config['name'] ?? 'TV-экран '.$screen }}" required></label>
     <label>Фон <select name="background_fit"><option value="cover" @selected(($config['background_fit'] ?? 'cover')==='cover')>Заполнить</option><option value="contain" @selected(($config['background_fit'] ?? '')==='contain')>Вместить</option><option value="stretch" @selected(($config['background_fit'] ?? '')==='stretch')>Растянуть</option></select></label>
     <label>Интервал переключения (сек.) <input type="number" min="0" max="3600" name="rotation_seconds" value="{{ $config['rotation_seconds'] ?? 0 }}"></label>
     <button type="submit">Сохранить</button>
    </form>
    <form method="POST" enctype="multipart/form-data" action="{{ route('employee.organization.admin.tv.background.store',$organization) }}">@csrf
     <input type="hidden" name="screen" value="{{ $screen }}"><label>Фон экрана <input type="file" name="background" accept="image/png,image/jpeg,image/webp" required></label><button type="submit">Загрузить фон</button>
    </form>
    @if(!empty($config['background_cid']))
     <form method="POST" action="{{ route('employee.organization.admin.tv.background.delete',$organization) }}">@csrf @method('DELETE')<input type="hidden" name="screen" value="{{ $screen }}"><button class="danger" type="submit" onclick="return confirm('Удалить фон этого экрана?')">Удалить фон</button></form>
    @endif
    @if(count($tvScreens)>1)
     <form method="POST" action="{{ route('employee.organization.admin.tv.screens.delete',[$organization,$screen]) }}">@csrf @method('DELETE')<input type="hidden" name="expected_screens_count" value="{{ count($tvScreens) }}"><button class="danger" type="submit" onclick="return confirm('Удалить экран и перенумеровать последующие?')">Удалить экран</button></form>
    @endif
   </div>
  @endforeach
 </section>
</div>
@endsection
