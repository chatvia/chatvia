<?php

namespace App\Http\Controllers\Settings\Account;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two-factor authentication settings page.
 */
class TwoFactorController
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('account/TwoFactor', [
            'enabled' => $user->two_factor_secret !== null,
            'confirmed' => $user->two_factor_confirmed_at !== null,
        ]);
    }
}
