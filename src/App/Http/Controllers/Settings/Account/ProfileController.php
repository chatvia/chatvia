<?php

namespace App\Http\Controllers\Settings\Account;

use App\Workspace\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Account\DeleteAccountRequest;
use App\Http\Requests\Settings\Account\ProfileUpdateRequest;
use App\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Workspace\Actions\Account\UpdateProfileAction;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request, UpdateProfileAction $updateProfile): RedirectResponse
    {
        $updateProfile->execute($request->toData());

        return to_route('profile.edit');
    }

    public function destroy(DeleteAccountRequest $request, DeleteUserAccountAction $deleteAccount): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Auth::logout();

        $deleteAccount->execute($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
