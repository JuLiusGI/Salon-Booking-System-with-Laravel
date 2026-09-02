<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);

        /*
         * Changing a password is what someone does when they think their account
         * has been reached by somebody else, so it has to end that person's
         * access too. Without this the attacker's existing session survives the
         * very action taken to stop it.
         *
         * This works together with the AuthenticateSession middleware, which is
         * what actually invalidates the other sessions on their next request.
         */
        Auth::logoutOtherDevices($request->string('password')->toString());

        // Recorded without the password, old or new.
        app(AuditLogger::class)->record('user.password_changed', $user);

        return back()->with('success', 'Password updated. Any other devices signed in as you have been signed out.');
    }
}
