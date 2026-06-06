<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Events\AccountDeletionRequested;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Step 1 of account deletion: mint a temporary signed confirmation link (valid
 * 30 minutes) and dispatch AccountDeletionRequested so the link is emailed. The
 * password has already been verified by the FormRequest; this is the second
 * factor. Nothing is deleted here — see {@see DeleteUserAccountAction}.
 */
class RequestAccountDeletionAction
{
    private const LIFETIME_MINUTES = 30;

    public function execute(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'settings.account.delete.confirm',
            now()->addMinutes(self::LIFETIME_MINUTES),
            ['userId' => $user->id],
        );

        AccountDeletionRequested::dispatch($user->email, $url, $user->name);
    }
}
