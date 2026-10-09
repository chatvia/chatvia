<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Records that the signed-in user finished or skipped the first-run welcome
 * tour.
 */
class CompleteOnboardingController
{
    /**
     * Mark the signed-in user as onboarded — fired when they finish or skip the
     * first-run welcome tour. A trivial single-column timestamp write with no
     * rule, transaction, or event, so it stays inline here per the Actions
     * carve-out in CLAUDE.md rather than behind an Action.
     *
     * Idempotent: the timestamp is stamped once and left alone on repeat calls,
     * so the original onboarding moment is preserved.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->onboarded_at === null) {
            $user->update(['onboarded_at' => now()]);
        }

        return back();
    }
}
