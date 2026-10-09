<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Identity\Workspace\Actions\Account\RemoveProfilePhotoAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Removes a signed-in chatter's profile photo.
 */
class ChatProfilePhotoDestroyController
{
    public function __invoke(
        Request $request,
        RemoveProfilePhotoAction $remove,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $remove->execute($user);

        return back();
    }
}
