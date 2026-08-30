<?php

use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\StaffController;
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

    // Administration. The role middleware is a coarse gate; the policies still
    // make the per-record decisions inside each controller.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');

        // Bound explicitly by id. Service and ServiceCategory resolve by slug by
        // default, which is right for public URLs but wrong here: an admin
        // renaming a record would change its own edit URL mid-flight.
        Route::get('categories', [ServiceCategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/create', [ServiceCategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [ServiceCategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category:id}/edit', [ServiceCategoryController::class, 'edit'])->name('categories.edit');
        Route::match(['put', 'patch'], 'categories/{category:id}', [ServiceCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category:id}', [ServiceCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('services/{service:id}/edit', [ServiceController::class, 'edit'])->name('services.edit');
        Route::match(['put', 'patch'], 'services/{service:id}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service:id}', [ServiceController::class, 'destroy'])->name('services.destroy');

        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::match(['put', 'patch'], 'staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });
});
