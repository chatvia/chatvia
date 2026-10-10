<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\DeleteAccountRequest;
use App\Identity\Workspace\Actions\Account\RequestAccountDeletionAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Step 1 of deleting a workspace account — the password is verified by the
 * FormRequest. Emails a signed link the owner must open to confirm the
 * deletion ({@see ConfirmAccountDeletionController}).
 */
class RequestAccountDeletionController
{
    public function __invoke(DeleteAccountRequest $request, RequestAccountDeletionAction $requestDeletion): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $requestDeletion->execute($user);

        return back()->with('success', __('We sent a confirmation link to your email. It expires in 30 minutes.'));
    }
}
