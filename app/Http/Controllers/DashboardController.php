<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The authenticated landing page.
     *
     * This is intentionally a signed-in home screen, not the analytics dashboard
     * described in MASTER_SPEC section 14, which is built in Phase 9.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'role' => $request->user()->role,
        ]);
    }
}
