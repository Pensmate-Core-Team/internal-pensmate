<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MemberActivationService;
use Illuminate\Http\Request;

class MemberActivationController extends Controller
{
    public function store(Request $request, User $member, MemberActivationService $service)
    {
        $issued = $service->issue($request->user(), $member);

        // Do not flash the bearer URL into session storage or put it in audit logs.
        return response()->view('members.activation-link', [
            'member' => $member->fresh(), 'activation' => $issued['link'],
            'activationUrl' => route('activation.show', ['token' => $issued['token']]),
        ]);
    }

    public function destroy(Request $request, User $member, MemberActivationService $service)
    {
        $service->revoke($request->user(), $member);

        return redirect()->route('members.show', $member)->with('success', 'Link aktivasi dicabut. Link tersebut tidak dapat digunakan lagi.');
    }
}
