<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\SetPasswordRequest;
use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-time password setup for accounts provisioned by the trial-checkout
 * webhook. Those accounts are created with a random password nobody knows;
 * RequirePasswordSetup funnels their owners here until they choose one.
 */
class SetPasswordController
{
    public function show(Request $request, PasswordSetup $passwordSetup): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $passwordSetup->isRequired($user)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/SetPassword', [
            'email' => $user->email,
        ]);
    }

    public function store(SetPasswordRequest $request, UpdatePasswordAction $updatePassword): RedirectResponse
    {
        $updatePassword->execute($request->toData());

        return redirect()->route('dashboard');
    }
}
