<?php

namespace App\Http\Controllers\Chat;

use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatShowLoginController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('auth/LoginMember', [
            'agent' => Agent::resolveAgentFromSubdomain($request->subdomain()),
        ]);
    }
}
