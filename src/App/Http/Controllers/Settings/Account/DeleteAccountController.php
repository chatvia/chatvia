<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Account\DeleteAccountRequest;
use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DeleteAccountController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/DeleteAccount');
    }

    public function destroy(
        DeleteAccountRequest $request,
        DeleteUserAccountAction $deleteAccount,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        Auth::logout();

        $deleteAccount->execute($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
