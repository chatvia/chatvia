<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelMembersAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelMembersController
{
    public function __invoke(Request $request, CancelMembersAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user);

        return back();
    }
}
