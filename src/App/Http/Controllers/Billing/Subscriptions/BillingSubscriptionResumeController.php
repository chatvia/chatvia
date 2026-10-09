<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\ResumeSubscriptionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resumes a plan subscription that was cancelled but is still in its grace
 * period.
 */
class BillingSubscriptionResumeController
{
    public function __invoke(Request $request, ResumeSubscriptionAction $resume): ?RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $resume->execute($user)) {
            return back()->with('error', __('No subscription found.'));
        }

        return null;
    }
}
