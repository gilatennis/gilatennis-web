<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        $fallback = $user?->isAdmin()
            ? '/admin/dashboard'
            : Fortify::redirects('login');

        return redirect()->intended($fallback);
    }
}
