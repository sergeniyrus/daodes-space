<?php

use App\\Http\\Controllers\\Api\\Mobile\\V1\\AuthController;
use App\\Http\\Controllers\\Api\\Mobile\\V1\\OrganizationController;
use Illuminate\\Support\\Facades\\Route;

Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'application' => 'DAODES',
        'api_version' => 'v1',
    ]))->name('health');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->name('organizations.index');

    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])
        ->whereNumber('organization')
        ->name('organizations.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});
