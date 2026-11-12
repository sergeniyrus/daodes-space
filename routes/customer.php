<?php

use App\Http\Controllers\Customer\CustomerAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get(
            '/login',
            [CustomerAuthController::class, 'create']
        )->name('login');

        Route::post(
            '/login/request',
            [CustomerAuthController::class, 'requestCode']
        )
            ->middleware('throttle:5,1')
            ->name('login.request');

        Route::post(
            '/login/verify',
            [CustomerAuthController::class, 'verifyCode']
        )
            ->middleware('throttle:10,1')
            ->name('login.verify');

        Route::post(
            '/logout',
            [CustomerAuthController::class, 'destroy']
        )
            ->middleware('auth:customer')
            ->name('logout');
    });
