<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Account\ProfileUpdateRequest;
use App\Identity\Workspace\Actions\Account\RemoveProfilePhotoAction;
use App\Identity\Workspace\Actions\Account\UpdateProfileAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function destroyPhoto(Request $request, RemoveProfilePhotoAction $removePhoto): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $removePhoto->execute($user);

        return to_route('profile.edit');
    }
}
