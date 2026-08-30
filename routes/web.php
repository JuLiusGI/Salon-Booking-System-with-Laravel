<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\BookingEntryController;
use App\Http\Controllers\Public\PublicPageController;
use Illuminate\Support\Facades\Route;

/*
 * Public salon website. Open to guests.
 */
Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('services', [PublicPageController::class, 'services'])->name('services');
Route::get('team', [PublicPageController::class, 'team'])->name('team');
Route::get('gallery', [PublicPageController::class, 'gallery'])->name('gallery');
Route::get('about', [PublicPageController::class, 'about'])->name('about');
Route::get('contact', [PublicPageController::class, 'contact'])->name('contact');

// Stable target for the booking call to action. Phase 6 takes this over.
Route::get('book', BookingEntryController::class)->name('booking.start');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('password.update');

    // Administration. The role middleware is a coarse gate; UserPolicy still
    // makes the per-record decisions inside the controller.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
    });
});
