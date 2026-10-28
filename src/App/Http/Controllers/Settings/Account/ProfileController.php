<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\ProfileUpdateRequest;
use App\Identity\Workspace\Actions\Account\UpdateProfileAction;
use App\Identity\Workspace\Enums\DateFormat;
use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The profile settings page: name, email and profile photo (removing the
 * photo is {@see ProfilePhotoController}).
 */
class ProfileController
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard/account/pages/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'dateFormats' => DateFormat::options(),
            // Teammates belong to someone else's workspace — surface which one,
            // so they know it isn't a standalone workspace.
            'managedBy' => $user->isTeammate() ? $user->workspace()->name : null,
        ]);
    }

    public function update(ProfileUpdateRequest $request, UpdateProfileAction $updateProfile): RedirectResponse
    {
        $updateProfile->execute($request->toData());

        return to_route('account.profile.edit');
    }
}
