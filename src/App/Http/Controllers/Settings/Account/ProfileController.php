<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\ProfileUpdateRequest;
use App\Identity\Workspace\Actions\Account\RemoveProfilePhotoAction;
use App\Identity\Workspace\Actions\Account\UpdateProfileAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Support\Enums\User\DateFormat;

class ProfileController
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('account/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'dateFormats' => DateFormat::options(),
            // Teammates (employees) belong to a workspace owner — surface who
            // manages this account so they know it isn't a standalone workspace.
            'managedBy' => $user->isEmployee() ? $user->parentWorkspace?->name : null,
        ]);
    }

    public function update(ProfileUpdateRequest $request, UpdateProfileAction $updateProfile): RedirectResponse
    {
        $updateProfile->execute($request->toData());

        return to_route('account.profile.edit');
    }

    public function destroyPhoto(Request $request, RemoveProfilePhotoAction $removePhoto): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $removePhoto->execute($user);

        return to_route('account.profile.edit');
    }
}
