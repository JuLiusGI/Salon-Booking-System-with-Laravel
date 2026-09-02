<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');

    // Registration is open to the public and creates a row every time, so it is
    // throttled like the other write endpoints here. Ten an hour is far above
    // anything a real person does and far below anything worth scripting.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:10,60');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');

    // A second line of defence behind the per-email limiter in LoginRequest,
    // which a spray across many addresses from one host would otherwise miss.
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:30,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Throttled independently of login: this endpoint sends mail, so it is a
    // spam vector as well as an enumeration one.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
