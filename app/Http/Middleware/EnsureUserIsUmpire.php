<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsUmpire
{
    /**
     * Guards the umpire scoring area. Guests are bounced to the umpire login
     * (not the admin one), and only umpires or admins may enter.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('umpire.login'));
        }

        if (Gate::denies('access-umpire')) {
            abort(403);
        }

        return $next($request);
    }
}
