<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\RemoveProfilePhotoAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes a signed-in chatter's profile photo.
 */
class ChatProfilePhotoDestroyController
{
    public function __invoke(
        Request $request,
        RemoveProfilePhotoAction $remove,
    ): RedirectResponse {
        $chatter = $request->user();

        // Staff edit their profile on the dashboard, not in a chat.
        abort_unless($chatter instanceof Chatter, Response::HTTP_FORBIDDEN);

        $remove->execute($chatter);

        return back();
    }
}
