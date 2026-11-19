<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateMemberRequest;
use App\Services\MemberActivationService;

class ActivationController extends Controller
{
    public function show(string $token, MemberActivationService $service)
    {
        $link = $service->resolve($token);
        if (! $link) {
            return response()->view('auth.activation-unavailable', [], 410);
        }

        return response()->view('auth.activate', ['activation' => $link, 'member' => $link->user, 'token' => $token]);
    }

    public function store(ActivateMemberRequest $request, string $token, MemberActivationService $service)
    {
        $user = $service->activate($token, $request->validated());
        if (! $user) {
            return response()->view('auth.activation-unavailable', [], 410);
        }
        $request->session()->regenerate();

        return redirect()->route('login')->with('success', 'Akun berhasil diaktifkan. Masuk dengan email dan password yang baru kamu buat.');
    }
}
