<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Data\Account\PasskeyRowData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passkeys\Passkey;

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
                ->get(['id', 'name', 'last_used_at', 'created_at'])
                ->map(fn (Passkey $passkey): PasskeyRowData => PasskeyRowData::fromModel($passkey)),
        ]);
    }
}
