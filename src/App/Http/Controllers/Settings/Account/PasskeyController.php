<?php

namespace App\Http\Controllers\Settings\Account;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the passkeys settings page with the signed-in user's own passkeys,
 * newest first. Adding and removing passkeys go through the authentication
 * package's own routes.
 */
class PasskeyController
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
