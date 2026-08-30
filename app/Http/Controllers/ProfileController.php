<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('customerProfile');

        return Inertia::render('Profile/Edit', [
            'profile' => $user->customerProfile?->only(
                'birthday', 'gender', 'address', 'preferences', 'allergies',
            ),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $user->fill($request->safe()->only('name', 'email', 'phone'));

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            // Only customers keep a profile record; staff have no use for
            // birthday, allergies, or service preferences.
            if ($user->isCustomer()) {
                $user->customerProfile()->updateOrCreate([], $request->safe()->only(
                    'birthday', 'gender', 'address', 'preferences', 'allergies',
                ));
            }
        });

        return back()->with('success', 'Profile updated.');
    }
}
