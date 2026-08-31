<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The target of every "Book appointment" call to action.
 *
 * This exists so every call to action has one stable URL. Guests need an account
 * before they can book, so they are sent to register with the destination
 * remembered. Salon staff have no customer booking flow of their own, so they go
 * to their dashboard.
 */
class BookingEntryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->user()) {
            // Remembered so the visitor lands back here once they have signed in.
            $request->session()->put('url.intended', route('booking.start'));

            return redirect()->route('register');
        }

        // Staff do not book as customers; booking on a customer's behalf is
        // part of appointment management rather than this flow.
        if (! $request->user()->isCustomer()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('booking.create');
    }
}
