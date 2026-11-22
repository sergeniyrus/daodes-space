<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use App\Http\Controllers\UserStatusController;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\AppReleaseController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Middleware\ReleaseAdmin;

/*
|--------------------------------------------------------------------------
| Telegram webhook
|--------------------------------------------------------------------------
*/

// Route::post('/telegram/webhook', [TelegramBotController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| ONLINE API
|--------------------------------------------------------------------------
|
| sendBeacon использует cookie → web-сессия и auth.
| CSRF отключён только для этого endpoint, как в исходной реализации.
|
*/
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/user/online', function () {
        Log::info('📡 ONLINE ROUTE HIT', [
            'user_id' => auth()->id(),
            'time' => now()->toDateTimeString(),
            'route' => request()->path(),
        ]);

        return app(UserStatusController::class)->online(request());
    })
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('user.online');
});

/*
|--------------------------------------------------------------------------
| PAYMENT PROVIDER WEBHOOKS
|--------------------------------------------------------------------------
|
| Отдельный API endpoint без пользовательской сессии и CSRF.
| Онлайн-провайдер должен быть явно зарегистрирован в config/payments.php.
|
*/
Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->where('provider', '[a-z0-9_-]{1,64}')
    ->middleware('throttle:120,1')
    ->name('payments.webhook');

Route::get('/app/version', [AppVersionController::class, 'check']);

/*
|--------------------------------------------------------------------------
| DAODES AI ENGINE — ADMIN ONLY
|--------------------------------------------------------------------------
|
| Сохраняем прежние URL, но включаем web session, auth и ReleaseAdmin.
| ReleaseAdmin в текущем проекте разрешает доступ пользователю с ID = 3.
|
*/
Route::middleware(['web', 'auth', ReleaseAdmin::class])->group(function () {
    Route::get('/ai/patches', [AIController::class, 'patches']);
    Route::post('/ai/patch/approve', [AIController::class, 'approve']);
    Route::post('/ai/patch/reject', [AIController::class, 'reject']);
});

Route::get(
    '/organizations/{organization}/menu',
    [MenuController::class, 'index']
);

Route::get(
    '/recipe-calculator/releases/latest',
    [AppReleaseController::class, 'latest']
);

Route::get(
    '/app/releases/{release}/download',
    [AppReleaseController::class, 'download']
)->name('releases.download');

/*
|--------------------------------------------------------------------------
| RELEASE ADMIN PANEL
|--------------------------------------------------------------------------
|
| Управление релизами приложения «Ёлки Иголки».
| Существующие маршруты управления релизами не изменены.
|
*/

// Route::middleware([
//     'auth',
//     ReleaseAdmin::class,
// ])
//     ->prefix('admin/releases')
//     ->name('admin.releases.')
//     ->group(function () {
//
//         Route::get('/', [ReleaseAdminController::class, 'index'])->name('index');
//         Route::get('/create', [ReleaseAdminController::class, 'create'])->name('create');
//         Route::post('/', [ReleaseAdminController::class, 'store'])->name('store');
//         Route::get('/{release}/edit', [ReleaseAdminController::class, 'edit'])->name('edit');
//         Route::put('/{release}', [ReleaseAdminController::class, 'update'])->name('update');
//         Route::delete('/{release}', [ReleaseAdminController::class, 'destroy'])->name('destroy');
//         Route::post('/{release}/activate', [ReleaseAdminController::class, 'activate'])->name('activate');
//     });

/*
|--------------------------------------------------------------------------
| DAODES Mobile API v1
|--------------------------------------------------------------------------
|
| Версионированный API мобильного приложения. Существующие endpoints выше
| остаются без изменений и сохраняют прежние URL и middleware.
|
*/
Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'application' => 'DAODES',
        'api_version' => 'v1',
    ]))->name('health');

    Route::post('/auth/login', [\App\Http\Controllers\Api\Mobile\V1\AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::get('/organizations', [\App\Http\Controllers\Api\Mobile\V1\OrganizationController::class, 'index'])
        ->name('organizations.index');

    Route::get('/organizations/{organization}', [\App\Http\Controllers\Api\Mobile\V1\OrganizationController::class, 'show'])
        ->whereNumber('organization')
        ->name('organizations.show');

    Route::middleware(['auth:sanctum', \\App\\Http\\Middleware\\EnsureMobileApiAbility::class])->group(function () {
        Route::get('/cart', [\App\Http\Controllers\Api\Mobile\V1\CartController::class, 'show'])->name('cart.show');
        Route::post('/cart/items', [\App\Http\Controllers\Api\Mobile\V1\CartController::class, 'add'])->name('cart.items.add');
        Route::patch('/cart/items/{menuItem}', [\App\Http\Controllers\Api\Mobile\V1\CartController::class, 'update'])->whereNumber('menuItem')->name('cart.items.update');
        Route::delete('/cart/items/{menuItem}', [\App\Http\Controllers\Api\Mobile\V1\CartController::class, 'remove'])->whereNumber('menuItem')->name('cart.items.remove');
        Route::delete('/cart', [\App\Http\Controllers\Api\Mobile\V1\CartController::class, 'clear'])->name('cart.clear');

        Route::get('/addresses', [\App\Http\Controllers\Api\Mobile\V1\AddressController::class, 'index'])->name('addresses.index');
        Route::post('/addresses', [\App\Http\Controllers\Api\Mobile\V1\AddressController::class, 'store'])->name('addresses.store');
        Route::put('/addresses/{address}', [\App\Http\Controllers\Api\Mobile\V1\AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
        Route::delete('/addresses/{address}', [\App\Http\Controllers\Api\Mobile\V1\AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');

        Route::get('/checkout', [\App\Http\Controllers\Customer\CustomerCheckoutController::class, 'create'])->name('checkout.quote');
        Route::post('/checkout', [\App\Http\Controllers\Customer\CustomerCheckoutController::class, 'store'])->name('checkout.store');

        Route::get('/orders', [\App\Http\Controllers\Api\Mobile\V1\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}/updates', [\App\Http\Controllers\Api\Mobile\V1\OrderController::class, 'updates'])->whereNumber('order')->name('orders.updates');
        Route::get('/orders/{order}', [\App\Http\Controllers\Api\Mobile\V1\OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::post('/orders/{order}/pickup/confirm', [\App\Http\Controllers\Api\Mobile\V1\OrderController::class, 'confirmPickup'])->whereNumber('order')->name('orders.pickup.confirm');

        Route::get('/notifications', [\App\Http\Controllers\Api\Mobile\V1\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Api\Mobile\V1\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\Mobile\V1\NotificationController::class, 'markRead'])->whereNumber('notification')->name('notifications.read');

        Route::post('/push/devices', [\App\Http\Controllers\Api\Mobile\V1\PushDeviceController::class, 'store'])->middleware('throttle:10,1')->name('push.devices.store');
        Route::delete('/push/devices', [\App\Http\Controllers\Api\Mobile\V1\PushDeviceController::class, 'destroy'])->middleware('throttle:20,1')->name('push.devices.destroy');

        Route::get('/me', [\App\Http\Controllers\Api\Mobile\V1\AuthController::class, 'me'])
            ->name('me');
        Route::post('/auth/logout', [\App\Http\Controllers\Api\Mobile\V1\AuthController::class, 'logout'])
            ->name('auth.logout');
    });
});

