<?php

use App\Http\Controllers\Employee\EmployeeAuthenticatedSessionController;
use App\Http\Controllers\Employee\EmployeeDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| РАБОЧИЙ КАБИНЕТ СОТРУДНИКОВ
|--------------------------------------------------------------------------
|
| Отдельный guard employee. Обычная авторизация DAODES (web)
| и существующие маршруты не изменяются.
|
*/

Route::prefix('staff')
    ->name('employee.')
    ->group(function () {

        Route::get(
            '/login',
            [EmployeeAuthenticatedSessionController::class, 'create']
        )->name('login');

        Route::post(
            '/login',
            [EmployeeAuthenticatedSessionController::class, 'store']
        )
            ->middleware('throttle:10,1')
            ->name('login.store');

        Route::middleware('employee.auth')->group(function () {

            Route::get(
                '/',
                [EmployeeDashboardController::class, 'index']
            )->name('dashboard');

            Route::post(
                '/logout',
                [EmployeeAuthenticatedSessionController::class, 'destroy']
            )->name('logout');
        });
    });