<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Actions\Account\RemoveProfilePhotoAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's profile photo. Uploading rides the profile form
 * ({@see ProfileController}); this removes it.
 */
class ProfilePhotoController
{
    public function destroy(Request $request, RemoveProfilePhotoAction $removePhoto): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $removePhoto->execute($user);

        return to_route('account.profile.edit');
    }
}
