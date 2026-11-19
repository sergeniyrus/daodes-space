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
