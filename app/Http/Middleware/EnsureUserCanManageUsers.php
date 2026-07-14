<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Gate::denies('manage-users')) {
            abort(403);
        }

        return $next($request);
    }
}
