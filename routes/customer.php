<?php

use App\Http\Controllers\Customer\CustomerAuthController;
use App\Http\Controllers\Customer\CustomerCatalogController;
use App\Http\Controllers\Customer\CustomerCartController;
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

        // Публичный каталог и гостевая корзина.
        Route::get(
            '/organizations/{organization}',
            [CustomerCatalogController::class, 'index']
        )->name('catalog');

        Route::get(
            '/cart',
            [CustomerCartController::class, 'index']
        )->name('cart');

        Route::post(
            '/organizations/{organization}/cart/items',
            [CustomerCartController::class, 'add']
        )->name('cart.add');

        Route::put(
            '/cart/items/{menuItem}',
            [CustomerCartController::class, 'update']
        )->name('cart.update');

        Route::delete(
            '/cart/items/{menuItemId}',
            [CustomerCartController::class, 'remove']
        )->whereNumber('menuItemId')
            ->name('cart.remove');

        Route::delete(
            '/cart',
            [CustomerCartController::class, 'clear']
        )->name('cart.clear');
    });
