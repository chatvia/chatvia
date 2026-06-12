<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasskeyController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('account/Passkeys', [
            'passkeys' => $request->user()
                ->passkeys()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }
}
