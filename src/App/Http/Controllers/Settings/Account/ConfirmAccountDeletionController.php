<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Services\AccountDeletionConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Step 2a of deleting a workspace account — the landing of the signed email
 * link. Arms the deletion for this session and returns the owner to the page;
 * it never deletes (so email prefetchers can't trigger anything). The route is
 * `signed` and gated by `workspace.owner`; the `userId` segment binds the
 * link to the signed-in owner. The final click is
 * {@see DeleteAccountController::destroy()}.
 */
class ConfirmAccountDeletionController
{
    public function __invoke(Request $request, int $userId, AccountDeletionConfirmation $confirmation): RedirectResponse
    {
        abort_unless($request->user()->id === $userId, 403);

        $confirmation->confirm($request->session());

        return redirect()->route('account.delete');
    }
}
