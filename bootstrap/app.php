<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // Binds a session to the password it was created with, so changing a
            // password signs out every other session. Without it
            // Auth::logoutOtherDevices() has nothing to act on.
            AuthenticateSession::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Errors have to be answered twice over, because a page can be reached
         * two ways.
         *
         * A plain browser request gets the Blade page in resources/views/errors,
         * which is self-contained and needs no built assets. An Inertia request
         * is XHR: handing it HTML makes the client show a raw document in a
         * modal, so it gets the Error page component instead and stays inside
         * the app. The status code is preserved either way.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if (! $request->header('X-Inertia') || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            // An expired session is not really an error page: the person just
            // needs to sign in again, and saying so where they stand is kinder.
            if ($status === 419) {
                return back()->with('error', 'Your session expired. Please try again.');
            }

            return inertia('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
