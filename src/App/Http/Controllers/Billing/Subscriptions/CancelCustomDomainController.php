<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelCustomDomainAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelCustomDomainController
{
    public function __invoke(Request $request, CancelCustomDomainAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user);

        return back();
    }
}
