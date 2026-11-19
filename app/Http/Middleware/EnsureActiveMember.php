<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveMember
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->is_active || ! filled($request->user()?->email)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akses akun belum tersedia atau dinonaktifkan. Hubungi admin tim.']);
        }

        return $next($request);
    }
}
