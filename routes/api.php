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
use App\Http\Middleware\ReleaseAdmin;


/*
|--------------------------------------------------------------------------
| Telegram webhook
|--------------------------------------------------------------------------
*/

// Route::post('/telegram/webhook', [TelegramBotController::class, 'handle']);


Route::get('/app/version', [AppVersionController::class, 'check']);


/*
|--------------------------------------------------------------------------
| AI ADMIN API
|--------------------------------------------------------------------------
|
| Доступ только авторизованным администраторам.
| Авторизация через стандартную web-сессию Laravel.
|
*/

Route::middleware(['web', 'auth', 'admin'])
    ->prefix('ai')
    ->group(function () {

        Route::post('/patch/approve', [AIController::class, 'approve']);

        Route::post('/patch/reject', [AIController::class, 'reject']);

        Route::get('/patches', [AIController::class, 'patches']);

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
    
