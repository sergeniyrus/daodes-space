<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\KeywordResetPasswordController;
// use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
                ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    // Общая страница выбора типа входа.
    Route::get('login', function () {
        return view('auth.portal');
    })->name('login');

    // Существующая авторизация DAODES для партнёров.
    Route::get('partner/login', [AuthenticatedSessionController::class, 'create'])
                ->name('partner.login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [KeywordResetPasswordController::class, 'showKeywordForm'])
                ->name('password.request');

    Route::post('forgot-password', [KeywordResetPasswordController::class, 'submitKeyword'])
                ->middleware('throttle:5,1')
                ->name('partner.password.submit');

    Route::get('reset-password', [KeywordResetPasswordController::class, 'showResetForm'])
                ->name('partner.password.reset');

    Route::post('reset-password', [KeywordResetPasswordController::class, 'updatePassword'])
                ->middleware('throttle:5,1')
                ->name('partner.password.update');
});

Route::middleware('auth')->group(function () {
    // Route::get('verify-email', EmailVerificationPromptController::class)
    //             ->name('verification.notice');

    // Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    //             ->middleware(['signed', 'throttle:6,1'])
    //             ->name('verification.verify');

    // Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    //             ->middleware('throttle:6,1')
    //             ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
                ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');
});
