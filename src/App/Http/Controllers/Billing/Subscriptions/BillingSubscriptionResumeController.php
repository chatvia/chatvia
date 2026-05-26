<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Auth\Models\User;
use Domain\Billing\Actions\ResumeSubscriptionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingSubscriptionResumeController
{
    public function __invoke(Request $request, ResumeSubscriptionAction $resume): ?RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $resume->execute($user)) {
            return back()->with('error', 'No subscription found.');
        }

        return null;
    }
}
