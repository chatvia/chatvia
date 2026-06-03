<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelExtraAgentsAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelExtraAgentsController
{
    public function __invoke(Request $request, CancelExtraAgentsAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user);

        return back();
    }
}
