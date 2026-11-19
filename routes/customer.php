<?php

use App\Http\Controllers\Customer\CustomerAuthController;
use App\Http\Controllers\Customer\CustomerPasswordRecoveryController;
use App\Http\Controllers\Customer\CustomerCatalogController;
use App\Http\Controllers\Customer\CustomerCartController;
use App\Http\Controllers\Customer\CustomerCheckoutController;
use App\Http\Controllers\Customer\CustomerOrderController;
use App\Http\Controllers\Customer\CustomerPushController;
use App\Http\Controllers\Customer\CustomerFeedbackController;
use App\Http\Controllers\OrderNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->name('customer.')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'create'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'store'])
        ->middleware('throttle:10,1')->name('login.store');

    Route::get('/password/forgot', [CustomerPasswordRecoveryController::class, 'create'])
        ->name('password.request');
    Route::post('/password/forgot', [CustomerPasswordRecoveryController::class, 'sendCode'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::post('/password/reset', [CustomerPasswordRecoveryController::class, 'reset'])
        ->middleware('throttle:10,1')->name('password.update');

    Route::post('/logout', [CustomerAuthController::class, 'destroy'])
        ->middleware('auth:customer')->name('logout');

    Route::get('/organizations', [CustomerCatalogController::class, 'organizations'])
        ->name('organizations.index');
    Route::get('/organizations/{organization}', [CustomerCatalogController::class, 'index'])
        ->name('catalog');
    Route::get('/organizations/{organization}/reviews', [CustomerFeedbackController::class, 'publicReviews'])
        ->name('organizations.reviews');
    Route::get('/cart', [CustomerCartController::class, 'index'])->name('cart');
    Route::post('/organizations/{organization}/cart/items', [CustomerCartController::class, 'add'])
        ->name('cart.add');
    Route::put('/cart/items/{menuItem}', [CustomerCartController::class, 'update'])
        ->name('cart.update');
    Route::delete('/cart/items/{menuItemId}', [CustomerCartController::class, 'remove'])
        ->whereNumber('menuItemId')->name('cart.remove');
    Route::delete('/cart', [CustomerCartController::class, 'clear'])->name('cart.clear');
    Route::get('/checkout', [CustomerCheckoutController::class, 'create'])->name('checkout');
    Route::post('/checkout', [CustomerCheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/receipt/{token}', [CustomerOrderController::class, 'receipt'])
        ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('receipt');
    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/feedback', [CustomerFeedbackController::class, 'index'])->middleware('auth:customer')->name('feedback.index');

    Route::middleware('auth:customer')->group(function () {
        Route::get('/settings', [\App\Http\Controllers\Customer\CustomerSettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/profile', [\App\Http\Controllers\Customer\CustomerSettingsController::class, 'updateProfile'])->name('settings.profile.update');
        Route::put('/settings/addresses/{address}/favorite', [\App\Http\Controllers\Customer\CustomerSettingsController::class, 'toggleFavoriteAddress'])->whereNumber('address')->name('settings.addresses.favorite');
        Route::post('/settings/addresses', [\App\Http\Controllers\Customer\CustomerSettingsController::class, 'storeAddress'])->name('settings.addresses.store');
        Route::put('/settings/addresses/{address}', [\App\Http\Controllers\Customer\CustomerSettingsController::class, 'updateAddress'])->whereNumber('address')->name('settings.addresses.update');
        Route::delete('/settings/addresses/{address}', [\App\Http\Controllers\Customer\CustomerSettingsController::class, 'destroyAddress'])->whereNumber('address')->name('settings.addresses.destroy');
        Route::post('/feedback/organizations/{organization}', [CustomerFeedbackController::class, 'storeFeedback'])->middleware('throttle:5,1')->name('feedback.store');
        Route::post('/orders/{order}/review', [CustomerFeedbackController::class, 'storeReview'])->middleware('throttle:5,1')->name('orders.review.store');
        Route::post('/push/devices', [CustomerPushController::class, 'store'])
            ->middleware('throttle:10,1')->name('push.devices.store');
        Route::delete('/push/devices', [CustomerPushController::class, 'destroy'])
            ->middleware('throttle:20,1')->name('push.devices.destroy');
        Route::get('/orders/notifications', [OrderNotificationController::class, 'customerIndex'])
            ->name('orders.notifications');
        Route::post('/orders/{order}/pickup/confirm', [CustomerOrderController::class, 'confirmPickup'])
            ->whereNumber('order')
            ->name('orders.pickup.confirm');
        Route::post('/orders/notifications/read-all', [OrderNotificationController::class, 'markAllCustomerRead'])
            ->name('orders.notifications.read-all');
        Route::post('/orders/notifications/{notification}/read', [OrderNotificationController::class, 'markCustomerRead'])
            ->name('orders.notifications.read');
    });

    Route::get('/orders/{order}/updates', [CustomerOrderController::class, 'updates'])
        ->name('orders.updates');
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])
        ->name('orders.show');
});