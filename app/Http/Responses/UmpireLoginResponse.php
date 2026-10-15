<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class UmpireLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        if (! $user || (! $user->isUmpire() && ! $user->isAdminRole())) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/umpire/login')
                ->with('error', 'You do not have permission to access the umpire panel.');
        }

        return redirect($user->isAdminRole() ? '/admin/dashboard' : '/umpire');
    }
}
