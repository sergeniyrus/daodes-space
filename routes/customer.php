<?php

use App\Http\Controllers\Customer\CustomerAuthController;
use App\Http\Controllers\Customer\CustomerCatalogController;
use App\Http\Controllers\Customer\CustomerCartController;
use App\Http\Controllers\Customer\CustomerCheckoutController;
use App\Http\Controllers\Customer\CustomerOrderController;
use App\Http\Controllers\Customer\CustomerPushController;
use App\Http\Controllers\OrderNotificationController;
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

        // Оформление доступно только после подтверждения клиентского телефона.
        // Контроллер сохраняет intended URL и отправляет гостя на customer.login.
        Route::get(
            '/checkout',
            [CustomerCheckoutController::class, 'create']
        )->name('checkout');

        Route::post(
            '/checkout',
            [CustomerCheckoutController::class, 'store']
        )->name('checkout.store');

        // Публичный электронный чек: доступ по секретному токену из SMS.
        Route::get(
            '/receipt/{token}',
            [CustomerOrderController::class, 'receipt']
        )
            ->where('token', '[A-Za-z0-9]{64}')
            ->middleware('throttle:30,1')
            ->name('receipt');

        Route::get(
            '/orders',
            [CustomerOrderController::class, 'index']
        )->name('orders.index');

        Route::middleware('auth:customer')->group(function () {
            Route::post('/push/devices', [CustomerPushController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('push.devices.store');

            Route::delete('/push/devices', [CustomerPushController::class, 'destroy'])
                ->middleware('throttle:20,1')
                ->name('push.devices.destroy');

            Route::get(
                '/orders/notifications',
                [OrderNotificationController::class, 'customerIndex']
            )->name('orders.notifications');

            Route::post(
                '/orders/notifications/{notification}/read',
                [OrderNotificationController::class, 'markCustomerRead']
            )->name('orders.notifications.read');
        });

        Route::get(
            '/orders/{order}/updates',
            [CustomerOrderController::class, 'updates']
        )->name('orders.updates');

        Route::get(
            '/orders/{order}',
            [CustomerOrderController::class, 'show']
        )->name('orders.show');
    });
