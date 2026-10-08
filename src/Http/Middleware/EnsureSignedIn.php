<?php

declare(strict_types=1);

namespace Ruvelo\Comments\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes that change something. Guests get a 401 from the API,
 * or the app's login page when it has one. A fresh app has no `login`
 * route, and Laravel's own `auth` middleware fails with a 500 there; this
 * answers 403 instead.
 */
class EnsureSignedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 401, 'Sign in to comment.');

        if (Route::has('login')) {
            return redirect()->guest(route('login'));
        }

        abort(403, 'Sign in to comment.');
    }
}
