<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Addons\CancelAddonAction;
use Domain\Billing\Enums\Addon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cancels the Remove Branding add-on right away.
 */
class CancelRemoveBrandingController
{
    public function __invoke(Request $request, CancelAddonAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user, Addon::RemoveBranding);

        return back();
    }
}
