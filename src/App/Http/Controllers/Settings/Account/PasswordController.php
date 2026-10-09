<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\UpdatePasswordRequest;
use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The change-password settings page.
 */
class PasswordController
{
    public function edit(): Response
    {
        return Inertia::render('account/Password');
    }

    public function update(UpdatePasswordRequest $request, UpdatePasswordAction $updatePassword): RedirectResponse
    {
        $updatePassword->execute($request->toData());

        return back();
    }
}
