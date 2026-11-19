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
                Route::post('/orders/{order}/claim', [EmployeeOrderController::class, 'claimDelivery'])
                    ->name('orders.claim');
                Route::patch('/orders/{order}/status', [EmployeeOrderController::class, 'updateStatus'])
                    ->name('orders.status');
                Route::patch('/orders/{order}/payment', [EmployeeOrderController::class, 'markPaymentReceived'])
                    ->name('orders.payment');
            });

        Route::middleware(['employee.organization', 'employee.role:admin'])
            ->prefix('organizations/{organization}/admin')
            ->name('organization.admin.')
            ->group(function () {
                Route::get('/manage', [\App\Http\Controllers\Employee\EmployeeAdminOrganizationController::class, 'manage'])->name('manage');
                Route::put('/order-settings', [\App\Http\Controllers\OrganizationController::class, 'updateOrderSettings'])->name('order-settings.update');
                Route::post('/tv-screens', [\App\Http\Controllers\OrganizationTVController::class, 'createScreen'])->name('tv.screens.store');
                Route::patch('/tv-screens/{screen}/settings', [\App\Http\Controllers\OrganizationTVController::class, 'updateScreenSettings'])->whereNumber('screen')->name('tv.screens.settings');
                Route::delete('/tv-screens/{screen}', [\App\Http\Controllers\OrganizationTVController::class, 'deleteScreen'])->whereNumber('screen')->name('tv.screens.delete');
                Route::post('/tv-background', [\App\Http\Controllers\OrganizationTVController::class, 'uploadBackground'])->name('tv.background.store');
                Route::delete('/tv-background', [\App\Http\Controllers\OrganizationTVController::class, 'deleteBackground'])->name('tv.background.delete');
            });

        // Полный операционный доступ администратора к заказам организации.
        Route::middleware(['employee.organization', 'employee.role:admin'])
            ->prefix('organizations/{organization}/admin/orders')
            ->name('organization.admin.orders.')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Employee\EmployeeAdminOrderController::class, 'index'])->name('index');
                Route::get('/{order}', [\App\Http\Controllers\Employee\EmployeeAdminOrderController::class, 'show'])->whereNumber('order')->name('show');
                Route::patch('/{order}/status', [\App\Http\Controllers\Employee\EmployeeAdminOrderController::class, 'updateStatus'])->whereNumber('order')->name('status');
                Route::patch('/{order}/payment', [\App\Http\Controllers\Employee\EmployeeAdminOrderController::class, 'markPaid'])->whereNumber('order')->name('payment');
            });

        // Администратор организации управляет меню, но не персоналом,
        // настройками онлайн-заказов или TV-экранами.
        Route::middleware(['employee.organization', 'employee.role:admin'])
            ->prefix('organizations/{organization}/menu')
            ->name('organization.menu.')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\OrganizationMenuController::class, 'index'])->name('index');
                Route::get('/sections/create', [\App\Http\Controllers\OrganizationMenuController::class, 'createSection'])->name('sections.create');
                Route::post('/sections', [\App\Http\Controllers\OrganizationMenuController::class, 'storeSection'])->name('sections.store');
                Route::get('/sections/{section}/edit', [\App\Http\Controllers\OrganizationMenuController::class, 'editSection'])->name('sections.edit');
                Route::put('/sections/{section}', [\App\Http\Controllers\OrganizationMenuController::class, 'updateSection'])->name('sections.update');
                Route::delete('/sections/{section}', [\App\Http\Controllers\OrganizationMenuController::class, 'destroySection'])->name('sections.destroy');
                Route::patch('/sections/{section}/sort', [\App\Http\Controllers\OrganizationMenuController::class, 'updateSectionSort'])->name('sections.sort');
                Route::patch('/sections/{section}/toggle', [\App\Http\Controllers\OrganizationMenuController::class, 'toggleSection'])->name('sections.toggle');
                Route::patch('/sections/{section}/quick-settings', [\App\Http\Controllers\OrganizationMenuController::class, 'updateSectionQuickSettings'])->name('sections.quick_settings');
                Route::get('/sections/{section}/items/create', [\App\Http\Controllers\OrganizationMenuController::class, 'createItem'])->name('items.create');
                Route::post('/sections/{section}/items', [\App\Http\Controllers\OrganizationMenuController::class, 'storeItem'])->name('items.store');
                Route::get('/sections/{section}/items/{item}/edit', [\App\Http\Controllers\OrganizationMenuController::class, 'editItem'])->name('items.edit');
                Route::put('/sections/{section}/items/{item}', [\App\Http\Controllers\OrganizationMenuController::class, 'updateItem'])->name('items.update');
                Route::delete('/sections/{section}/items/{item}', [\App\Http\Controllers\OrganizationMenuController::class, 'destroyItem'])->name('items.destroy');
                Route::patch('/sections/{section}/items/{item}/sort', [\App\Http\Controllers\OrganizationMenuController::class, 'updateItemSort'])->name('items.sort');
                Route::patch('/sections/{section}/items/{item}/toggle', [\App\Http\Controllers\OrganizationMenuController::class, 'toggleItem'])->name('items.toggle');
            });
    });
});