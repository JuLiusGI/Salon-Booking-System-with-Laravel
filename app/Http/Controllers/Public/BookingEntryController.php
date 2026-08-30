<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The target of every "Book appointment" call to action.
 *
 * This exists so the CTA has one stable URL from the moment the public site is
 * built. Phase 6 replaces the body with the real booking workflow; until then it
 * routes visitors to the step they actually need first, which is an account.
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

        return redirect()->route('dashboard');
    }
}
