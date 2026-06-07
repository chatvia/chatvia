<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelRemoveBrandingAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelRemoveBrandingController
{
    public function __invoke(Request $request, CancelRemoveBrandingAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user);

        return back();
    }
}
