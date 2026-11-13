<?php

use App\Http\Controllers\Employee\EmployeeAuthenticatedSessionController;
use App\Http\Controllers\Employee\EmployeeDashboardController;
use App\Http\Controllers\Employee\EmployeeInvitationController;
use App\Http\Controllers\Employee\EmployeePasswordRecoveryController;
use App\Http\Controllers\Employee\EmployeeOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->name('employee.')->group(function () {
    Route::get('/login', [EmployeeAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [EmployeeAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')->name('login.store');

    Route::get('/password/forgot', [EmployeePasswordRecoveryController::class, 'create'])
        ->name('password.request');
    Route::post('/password/forgot', [EmployeePasswordRecoveryController::class, 'sendCode'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::post('/password/reset', [EmployeePasswordRecoveryController::class, 'reset'])
        ->middleware('throttle:10,1')->name('password.update');

    Route::get('/activate', [EmployeeInvitationController::class, 'create'])->name('activate');
    Route::post('/activate', [EmployeeInvitationController::class, 'store'])
        ->middleware('throttle:10,1')->name('activate.store');

    Route::middleware('employee.auth')->group(function () {
        Route::get('/', [EmployeeDashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout', [EmployeeAuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::middleware(['employee.organization', 'employee.role:cook,courier'])
            ->prefix('organizations/{organization}')->name('organization.')->group(function () {
                Route::get('/orders', [EmployeeOrderController::class, 'index'])->name('orders.index');
                Route::get('/orders/{order}', [EmployeeOrderController::class, 'show'])
                    ->whereNumber('order')
                    ->name('orders.show');
                Route::get('/orders/updates', [EmployeeOrderController::class, 'updates'])->name('orders.updates');
                Route::get('/orders/notifications', [\App\Http\Controllers\OrderNotificationController::class, 'employeeIndex'])
                    ->name('orders.notifications');
                Route::post('/orders/notifications/{notification}/read', [\App\Http\Controllers\OrderNotificationController::class, 'markEmployeeRead'])
                    ->name('orders.notifications.read');
                Route::patch('/orders/{order}/status', [EmployeeOrderController::class, 'updateStatus'])
                    ->name('orders.status');
            });
    });
});