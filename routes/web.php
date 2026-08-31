<?php

use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Booking\AppointmentController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Manage\AppointmentActionController;
use App\Http\Controllers\Manage\AppointmentController as ManageAppointmentController;
use App\Http\Controllers\Manage\CalendarController;
use App\Http\Controllers\Manage\StaffBookingController;
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

// Every call to action points here. Guests are sent to register first, keeping
// the destination, so they land back on the booking flow once signed in.
Route::get('book', BookingEntryController::class)->name('booking.start');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Customer booking.
    Route::get('book/new', [BookingController::class, 'create'])->name('booking.create');
    Route::post('book/new', [BookingController::class, 'store'])->name('booking.store');

    Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('appointments/{appointment:reference}', [AppointmentController::class, 'show'])
        ->name('appointments.show');

    // Acting on an appointment. Shared by customers and staff; who may do what is
    // decided by AppointmentPolicy, not by which screen the request came from.
    Route::post('appointments/{appointment:reference}/cancel', [AppointmentActionController::class, 'cancel'])
        ->name('appointments.cancel');
    Route::get('appointments/{appointment:reference}/reschedule', [AppointmentActionController::class, 'editSchedule'])
        ->name('appointments.reschedule');
    Route::post('appointments/{appointment:reference}/reschedule', [AppointmentActionController::class, 'reschedule'])
        ->name('appointments.reschedule.store');

    // The salon's diary. Stylists reach it too, seeing only their own work.
    Route::prefix('manage')->name('manage.')->group(function () {
        Route::get('calendar', CalendarController::class)->name('calendar');

        Route::get('appointments', [ManageAppointmentController::class, 'index'])->name('appointments.index');
        Route::get('appointments/new', [StaffBookingController::class, 'create'])->name('appointments.create');
        Route::post('appointments/new', [StaffBookingController::class, 'store'])->name('appointments.store');
        Route::get('appointments/{appointment:reference}', [ManageAppointmentController::class, 'show'])
            ->name('appointments.show');
        Route::patch('appointments/{appointment:reference}', [ManageAppointmentController::class, 'update'])
            ->name('appointments.update');
        Route::post('appointments/{appointment:reference}/status', [AppointmentActionController::class, 'transition'])
            ->name('appointments.status');
    });

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

        // Scheduling: everything the availability engine reads.
        Route::get('schedule/hours', [ScheduleController::class, 'editHours'])->name('schedule.hours');
        Route::put('schedule/hours', [ScheduleController::class, 'updateHours'])->name('schedule.hours.update');

        Route::get('schedule/rules', [ScheduleController::class, 'editRules'])->name('schedule.rules');
        Route::put('schedule/rules', [ScheduleController::class, 'updateRules'])->name('schedule.rules.update');

        Route::get('schedule/exceptions', [ScheduleController::class, 'exceptions'])->name('schedule.exceptions');
        Route::post('schedule/exceptions', [ScheduleController::class, 'storeException'])->name('schedule.exceptions.store');
        Route::delete('schedule/exceptions/{exception}', [ScheduleController::class, 'destroyException'])->name('schedule.exceptions.destroy');

        Route::get('staff/{staff}/schedule', [ScheduleController::class, 'editStaffSchedule'])->name('staff.schedule');
        Route::put('staff/{staff}/schedule', [ScheduleController::class, 'updateStaffSchedule'])->name('staff.schedule.update');

        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::match(['put', 'patch'], 'staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });
});
